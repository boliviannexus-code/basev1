@php
    $nightItems = $record->accountStatement?->items?->where('type', 'lodging_night')->where('status', 'active')->filter(fn ($item) => $item->date)->groupBy(fn ($item) => $item->date->format('d/m/Y'));
    $nightLines = $nightItems?->map(fn ($items, $date) => $date.': '.money_format_decimal($items->sum('total')).' '.$record->currency)->values()->all() ?? [];
    if (! $nightLines && $record instanceof \App\Models\Reservation) {
        $nightLines = [money_format_decimal($record->price_per_person).' '.$record->currency];
    }
    if (! $nightLines && $record instanceof \App\Models\ReservationGroup) {
        $nightLines = $record->reservations->whereNotIn('status', ['cancelled', 'no_show'])->map(fn ($reservation) => $reservation->code.': '.money_format_decimal($reservation->price_per_person).' '.$reservation->currency)->all();
    }
    $isConfirmed = in_array($record->status, ['confirmed', 'checked_in'], true);
    $reservations = $record instanceof \App\Models\ReservationGroup
        ? $record->reservations->whereNotIn('status', ['cancelled', 'no_show'])
        : collect([$record]);
    $breakfastCount = $reservations->where('breakfast_included', true)->count();
    $translations = [
        'es' => ['¡Reserva confirmada! 🎉', 'Detalle de tu reserva 🏨', 'Gracias por elegir Hoteles Nexus', 'Aquí te compartimos el detalle de tu reserva:', 'Reserva', 'Nombre', 'Espacio', 'Noches', 'Costo por noche', 'Total', 'Adelanto registrado', 'Saldo pendiente', 'Servicios', 'Desayuno', 'Incluido', 'No incluido', 'Incluido solo en algunos espacios', 'Ducha caliente', 'Wi-Fi', 'Parqueo gratuito', 'Política de cancelación', 'Cancelación *antes de 48 horas* del check-in: sin penalización', 'Cancelación entre *48 y 24 horas antes*: penalización del 25% del total', 'Cancelación con *menos de 24 horas*: penalización del 50% del total', 'Cancelación el *mismo día* del check-in: penalización del 100% del total', 'Para cualquier duda o modificación, no dudes en escribirnos o llamarnos.', 'No disponible', ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado']],
        'en' => ['Your reservation is confirmed! 🎉', 'Your reservation details 🏨', 'Thank you for choosing Hoteles Nexus', 'Here are the details of your reservation:', 'Reservation', 'Name', 'Space', 'Nights', 'Price per night', 'Total', 'Deposit paid', 'Outstanding balance', 'Amenities', 'Breakfast', 'Included', 'Not included', 'Included for some spaces only', 'Hot shower', 'Wi-Fi', 'Free parking', 'Cancellation policy', 'Cancellation *more than 48 hours* before check-in: no penalty', 'Cancellation *between 48 and 24 hours* before check-in: 25% of the total', 'Cancellation *less than 24 hours* before check-in: 50% of the total', 'Cancellation *on the day* of check-in: 100% of the total', 'If you have any questions or need to make changes, please message or call us.', 'Not available', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']],
        'fr' => ['Votre réservation est confirmée ! 🎉', 'Détails de votre réservation 🏨', 'Merci d’avoir choisi Hoteles Nexus', 'Voici les détails de votre réservation :', 'Réservation', 'Nom', 'Espace', 'Nuits', 'Prix par nuit', 'Total', 'Acompte versé', 'Solde restant', 'Services', 'Petit-déjeuner', 'Inclus', 'Non inclus', 'Inclus pour certains espaces uniquement', 'Douche chaude', 'Wi-Fi', 'Parking gratuit', 'Politique d’annulation', 'Annulation *plus de 48 heures* avant le check-in : sans frais', 'Annulation *entre 48 et 24 heures* avant le check-in : 25 % du total', 'Annulation *moins de 24 heures* avant le check-in : 50 % du total', 'Annulation *le jour même* du check-in : 100 % du total', 'Pour toute question ou modification, écrivez-nous ou appelez-nous.', 'Non disponible', ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']],
        'pt' => ['Reserva confirmada! 🎉', 'Detalhes da sua reserva 🏨', 'Obrigado por escolher Hoteles Nexus', 'Aqui estão os detalhes da sua reserva:', 'Reserva', 'Nome', 'Espaço', 'Noites', 'Preço por noite', 'Total', 'Adiantamento pago', 'Saldo pendente', 'Comodidades', 'Café da manhã', 'Incluído', 'Não incluído', 'Incluído apenas em alguns espaços', 'Chuveiro quente', 'Wi-Fi', 'Estacionamento gratuito', 'Política de cancelamento', 'Cancelamento *com mais de 48 horas* de antecedência do check-in: sem multa', 'Cancelamento *entre 48 e 24 horas* antes do check-in: multa de 25% do total', 'Cancelamento *com menos de 24 horas* antes do check-in: multa de 50% do total', 'Cancelamento *no mesmo dia* do check-in: multa de 100% do total', 'Para dúvidas ou alterações, envie uma mensagem ou ligue para nós.', 'Não disponível', ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado']],
    ];
    $t = $translations[$language];
    $breakfast = $breakfastCount === 0 ? $t[15] : ($breakfastCount === $reservations->count() ? $t[14] : $t[16]);
    $paid = $record->accountStatement ? (float) $record->accountStatement->payments_total
        : ($record instanceof \App\Models\Reservation && $record->payment_status === 'validated' ? (float) $record->advance_amount : 0);
    $balance = (float) ($record->accountStatement?->balance ?? $record->balance_amount);
    $formatDate = fn ($date) => $t[27][$date->dayOfWeek].' '.$date->format('d/m/Y');
    $noDepositNotice = [
        'es' => '📌 Si no puedes venir o necesitas cancelar, te agradeceremos que nos avises con anticipación para poder ofrecer el espacio a otros huéspedes.',
        'en' => '📌 If you cannot come or need to cancel, please let us know in advance so we can offer the space to other guests.',
        'fr' => '📌 Si vous ne pouvez pas venir ou devez annuler, merci de nous prévenir à l’avance afin que nous puissions proposer cet espace à d’autres voyageurs.',
        'pt' => '📌 Se não puder vir ou precisar cancelar, avise-nos com antecedência para que possamos oferecer o espaço a outros hóspedes.',
    ];
    $copyText = implode("\n", array_merge([
        $isConfirmed ? $t[0] : $t[1], $t[2], $t[3], '',
        '📋 *'.$t[4].':* '.$record->code,
        '👤 *'.$t[5].':* '.$record->guest_name,
        '🛏️ *'.$t[6].':* '.$resourceNames,
        '📅 *Check-in:* '.$formatDate($record->check_in),
        '📅 *Check-out:* '.$formatDate($record->check_out),
        '🌙 *'.$t[7].':* '.$record->nights,
        '💰 *'.$t[8].':*',
    ], $nightLines ?: [$t[26]], [
        '*'.$t[9].':* '.money_format_decimal($record->total_amount).' '.$record->currency,
    ], $paid > 0 ? ['*'.$t[10].':* '.money_format_decimal($paid).' '.$record->currency] : [], [
        '*'.$t[11].':* '.money_format_decimal($balance).' '.$record->currency, '',
        '🛎️ *'.$t[12].':*', '- '.$t[13].': '.$breakfast, '- '.$t[17], '- '.$t[18], '- '.$t[19], '',
    ], $paid > 0 ? [
        '📌 *'.$t[20].':*', '- '.$t[21], '- '.$t[22], '- '.$t[23], '- '.$t[24],
    ] : [$noDepositNotice[$language]], ['', '📞 '.$t[25]]));
@endphp
{!! $copyText !!}
