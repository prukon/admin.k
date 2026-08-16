<?php

return [
    'contract_create_fee' => (float) env('BILLING_CONTRACT_CREATE_FEE', 70.00),
    'sms_send_fee' => (float) env('BILLING_SMS_SEND_FEE', 70.00),
];
