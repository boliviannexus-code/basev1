<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropIndex('players_company_name_index');
            $table->dropUnique('players_company_ci_normalized_unique');
        });

        // A player used to be unique only within a company, so the same person
        // may exist once per league. Keep the oldest record as the global
        // player and move every foreign-key reference before removing copies.
        DB::statement(
            'CREATE TEMPORARY TABLE player_duplicate_merge_map ON COMMIT DROP AS
             SELECT id AS duplicate_id,
                    MIN(id) OVER (PARTITION BY ci_normalized) AS canonical_id
             FROM players
             WHERE ci_normalized IS NOT NULL'
        );

        DB::statement(
            'DELETE FROM player_duplicate_merge_map
             WHERE duplicate_id = canonical_id'
        );

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                relation record;
            BEGIN
                FOR relation IN
                    SELECT child.relname AS table_name,
                           child_column.attname AS column_name
                    FROM pg_constraint constraint_definition
                    JOIN pg_class child
                      ON child.oid = constraint_definition.conrelid
                    JOIN pg_namespace child_namespace
                      ON child_namespace.oid = child.relnamespace
                    JOIN pg_class parent
                      ON parent.oid = constraint_definition.confrelid
                    JOIN pg_attribute child_column
                      ON child_column.attrelid = child.oid
                     AND child_column.attnum = constraint_definition.conkey[1]
                    JOIN pg_attribute parent_column
                      ON parent_column.attrelid = parent.oid
                     AND parent_column.attnum = constraint_definition.confkey[1]
                    WHERE constraint_definition.contype = 'f'
                      AND child_namespace.nspname = current_schema()
                      AND parent.relname = 'players'
                      AND parent_column.attname = 'id'
                      AND array_length(constraint_definition.conkey, 1) = 1
                LOOP
                    EXECUTE format(
                        'UPDATE %I SET %I = merge_map.canonical_id
                         FROM player_duplicate_merge_map merge_map
                         WHERE %I.%I = merge_map.duplicate_id',
                        relation.table_name,
                        relation.column_name,
                        relation.table_name,
                        relation.column_name
                    );
                END LOOP;
            END $$;
            SQL
        );

        DB::statement(
            'DELETE FROM players
             USING player_duplicate_merge_map
             WHERE players.id = player_duplicate_merge_map.duplicate_id'
        );

        DB::statement(
            "UPDATE players
             SET internal_code = 'N' || LPAD((id + 99)::text, 5, '0')"
        );

        Schema::table('players', function (Blueprint $table): void {
            $table->unique('ci_normalized', 'players_ci_normalized_unique');
            $table->unique('internal_code', 'players_internal_code_unique');
            $table->index(['last_name', 'first_name'], 'players_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropIndex('players_name_index');
            $table->dropUnique('players_internal_code_unique');
            $table->dropUnique('players_ci_normalized_unique');
            $table->unique(['company_id', 'ci_normalized'], 'players_company_ci_normalized_unique');
            $table->index(['company_id', 'last_name', 'first_name'], 'players_company_name_index');
        });
    }
};
