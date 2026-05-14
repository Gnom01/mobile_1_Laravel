<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Idempotency key — must be a valid UUID v4
            'guid'                         => ['required', 'string', 'uuid'],

            // CRM user IDs (student + payer)
            'usersID'                      => ['nullable', 'integer', 'min:1'],
            'payerUserId'                  => ['nullable', 'integer', 'min:1'],
            'payer_UsersID'                => ['nullable', 'integer', 'min:1'],

            // ── Top-level CRM fields (new Flutter format) ─────────────────────
            'productsID'                   => ['nullable', 'integer'],
            'coursesHeadingsID'            => ['nullable', 'integer'],
            'contractAmount'               => ['nullable', 'numeric', 'min:0'],
            'contractPeriodFrom'           => ['nullable', 'date_format:Y-m-d'],
            'dataTo'                       => ['nullable', 'date_format:Y-m-d'],
            'paymentMethodsDVID'           => ['nullable', 'integer'],
            'paymentMethodsP24'            => ['nullable', 'string', 'max:20'],
            'paymentCardID'                => ['nullable', 'string', 'max:100'],
            'buyerNIP'                     => ['nullable', 'string', 'max:20'],
            'purchaseKey'                  => ['nullable', 'string', 'max:255'],
            'returnUrl'                    => ['nullable', 'string', 'max:500'],
            'paymentStatusesDVID'          => ['nullable', 'integer'],
            'arrayOfSelectedInstallments'  => ['nullable', 'string'],
            'promotionsSalesIDList'        => ['nullable', 'string'],

            // ── Full installment schedule (new Flutter format) ────────────────
            'allInstallments'                              => ['nullable', 'array'],
            'allInstallments.*.countNumber'                => ['required_with:allInstallments', 'integer'],
            'allInstallments.*.paymentDate'                => ['required_with:allInstallments', 'date_format:Y-m-d'],
            'allInstallments.*.paymentPositionPrice'       => ['required_with:allInstallments', 'numeric'],
            'allInstallments.*.paymentPositionPriceDiscount' => ['nullable', 'numeric'],
            'allInstallments.*.isFullUnitOfAccount'        => ['nullable', 'integer', 'in:0,1'],
            'allInstallments.*.isVoid'                     => ['nullable', 'integer', 'in:0,1'],
            'allInstallments.*.periodFromDate'             => ['nullable', 'date_format:Y-m-d'],
            'allInstallments.*.periodToDate'               => ['nullable', 'date_format:Y-m-d'],
            'allInstallments.*.discountCash'               => ['nullable', 'numeric'],
            'allInstallments.*.discountProcent'            => ['nullable', 'numeric'],

            // ── Legacy fields (kept for backward compatibility) ───────────────
            'rawSelectedPricing'                          => ['nullable', 'array'],
            'rawSelectedPricing.productsID'               => ['nullable', 'integer'],
            'rawSelectedPricing.priceListsTemplatesPositionsID' => ['nullable', 'integer'],
            'rawSelectedPricing.amount'                   => ['nullable', 'numeric', 'min:0'],
            'rawSelectedPricing.unitAmount'               => ['nullable', 'numeric', 'min:0'],
            'rawSelectedPricing.paymentTypesDVID'         => ['nullable', 'integer'],
            'rawSelectedPricing.periodsOfValidityDVID'    => ['nullable', 'integer'],

            'rawCourseData'                               => ['nullable', 'array'],
            'rawCourseData.coursesHeadingsID'             => ['nullable', 'integer'],

            'payerUser'                    => ['nullable', 'array'],
            'payerUser.firstName'          => ['nullable', 'string', 'max:100'],
            'payerUser.lastName'           => ['nullable', 'string', 'max:100'],
            'payerUser.phone'              => ['nullable', 'string', 'max:30'],
            'payerUser.email'              => ['nullable', 'email', 'max:255'],

            'installments'                 => ['nullable', 'array'],
            'installments.*.paymentDate'   => ['nullable', 'date_format:Y-m-d'],
            'installments.*.amount'        => ['nullable', 'numeric'],

            'allInstallmentsPrice'         => ['nullable', 'numeric', 'min:0'],
            'entryFee'                     => ['nullable', 'numeric', 'min:0'],
            'contractStartDate'            => ['nullable', 'date'],
            'contractEndDate'              => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'guid.required' => 'Pole guid jest wymagane.',
            'guid.uuid'     => 'Pole guid musi być poprawnym UUID.',
        ];
    }
}
