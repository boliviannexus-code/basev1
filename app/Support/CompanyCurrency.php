<?php

namespace App\Support;

use App\Models\Company;

final class CompanyCurrency
{
    public const CURRENCIES = [
        'BOB' => ['Boliviano', 'Bs'], 'PEN' => ['Sol peruano', 'S/'], 'ARS' => ['Peso argentino', '$'],
        'BRL' => ['Real brasileño', 'R$'], 'CLP' => ['Peso chileno', '$'], 'COP' => ['Peso colombiano', '$'],
        'CRC' => ['Colón costarricense', '₡'], 'CUP' => ['Peso cubano', '$'], 'DOP' => ['Peso dominicano', 'RD$'],
        'GTQ' => ['Quetzal guatemalteco', 'Q'], 'HNL' => ['Lempira hondureña', 'L'], 'MXN' => ['Peso mexicano', '$'],
        'NIO' => ['Córdoba nicaragüense', 'C$'], 'PYG' => ['Guaraní paraguayo', '₲'], 'UYU' => ['Peso uruguayo', '$'],
        'VES' => ['Bolívar venezolano', 'Bs.'], 'PAB' => ['Balboa panameño', 'B/.'], 'HTG' => ['Gourde haitiano', 'G'],
    ];

    public static function code(Company|int|null $company = null): string
    {
        if (is_int($company)) {
            $key = 'currency.company.'.$company;
            $request = request();
            if ($request->attributes->has($key)) return $request->attributes->get($key);
            $record = Company::query()->withoutGlobalScopes()->find($company);
            $code = $record?->local_currency ?? 'BOB';
            $code = isset(self::CURRENCIES[$code]) ? $code : 'BOB';
            $request->attributes->set($key, $code);
            return $code;
        } elseif ($company === null) {
            $company = CompanyContext::activeCompany();
        }
        $code = $company?->local_currency ?? 'BOB';

        return isset(self::CURRENCIES[$code]) ? $code : 'BOB';
    }

    public static function symbol(Company|int|null $company = null): string
    {
        $code = self::code($company);
        $symbol = self::CURRENCIES[$code][1];

        return $symbol === '$' ? '$ '.$code : $symbol;
    }

    public static function display(?string $currency, Company|int|null $company = null): string
    {
        return strtoupper((string) $currency) === 'USD' ? 'USD' : self::code($company);
    }
}
