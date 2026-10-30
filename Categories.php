<?php

namespace Omnibank\Qonto;

use Omnibank\Model\TransactionCategory;

/**
 * Qonto's transaction categories and operation types (docs.qonto.com,
 * Business API, List transactions), as Omnibank's categories: the category
 * first, else what the operation type says on its own. "fallback" and
 * "pending" are Qonto's "not categorised yet": none.
 */
final class Categories
{
    public const CATEGORIES = [
        'restaurant_and_bar' => TransactionCategory::FOOD,
        'food_and_grocery' => TransactionCategory::FOOD,
        'transport' => TransactionCategory::TRANSPORT,
        'gas_station' => TransactionCategory::TRANSPORT,
        'hotel_and_lodging' => TransactionCategory::LEISURE,
        'it_and_electronics' => TransactionCategory::SHOPPING,
        'hardware_and_equipment' => TransactionCategory::BUSINESS,
        'office_supply' => TransactionCategory::BUSINESS,
        'office_rental' => TransactionCategory::HOUSING,
        'utility' => TransactionCategory::HOUSING,
        'insurance' => TransactionCategory::INSURANCE,
        'logistics' => TransactionCategory::BUSINESS,
        'online_service' => TransactionCategory::SUBSCRIPTIONS,
        'subscription' => TransactionCategory::SUBSCRIPTIONS,
        'legal_and_accounting' => TransactionCategory::BUSINESS,
        'marketing' => TransactionCategory::BUSINESS,
        'manufacturing' => TransactionCategory::BUSINESS,
        'other_service' => TransactionCategory::BUSINESS,
        'finance' => TransactionCategory::BANK_FEES,
        'fees' => TransactionCategory::BANK_FEES,
        'tax' => TransactionCategory::TAXES,
        'salary' => TransactionCategory::INCOME,
        'sales' => TransactionCategory::INCOME,
        'other_income' => TransactionCategory::INCOME,
        'refund' => TransactionCategory::INCOME,
        'atm' => TransactionCategory::CASH,
        'treasury_and_interco' => TransactionCategory::TRANSFER,
        'voucher' => TransactionCategory::SHOPPING,
        'other_expense' => TransactionCategory::OTHER,
    ];

    /** Categories that are Qonto's "none yet". */
    public const NONE = ['fallback', 'pending'];

    public const OPERATION_CATEGORIES = [
        'transfer' => TransactionCategory::TRANSFER,
        'swift_income' => TransactionCategory::TRANSFER,
        'income' => TransactionCategory::INCOME,
        'card_acquirer_payout' => TransactionCategory::INCOME,
        'account_remuneration' => TransactionCategory::INCOME,
        'qonto_fee' => TransactionCategory::BANK_FEES,
        'cheque' => TransactionCategory::CASH,
        'financing_installment' => TransactionCategory::LOAN,
        'pay_later' => TransactionCategory::LOAN,
        'investment' => TransactionCategory::SAVINGS,
        'f24' => TransactionCategory::TAXES,
    ];

    /** A transaction's category; null when Qonto says nothing (or not yet). */
    public static function of(array $line): ?TransactionCategory
    {
        $category = $line['category'] ?? null;
        if (\is_string($category) && '' !== $category && !\in_array($category, self::NONE, true)) {
            return self::CATEGORIES[$category] ?? TransactionCategory::UNKNOWN;
        }

        return self::OPERATION_CATEGORIES[strtolower((string) ($line['operation_type'] ?? ''))] ?? null;
    }
}
