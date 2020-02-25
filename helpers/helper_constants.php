<?php

const LIVE_MODE = true;

const DAYS_STD = 90;

const ADDED_MANUALLY = [
  "MANUAL",
  "WEBFORM"
];

const PICK_LIST_FOLDER_NAME = 'OLD';
const INVOICE_FOLDER_NAME   = 'OLD';  //Published

const PAYMENT_TOTAL_NEW_PATIENT = 6;

const PAYMENT_METHOD = [
  'COUPON'       => 'coupon',
  'MAIL'         => 'cheque',
  'ONLINE'       => 'cod',
  'AUTOPAY'      => 'stripe',
  'CARD EXPIRED' => 'stripe-card-expired'
];

const ORDER_STATUS_WC = [

  'confirm-transfer' => 'Confirming Order (Transfer)',
  'confirm-refill'   => 'Confirming Order (Refill Request)',
  'confirm-autofill' => 'Confirming Order (Autofill)',
  'confirm-new-rx'   => 'Confirming Order (Doctor Will Send Rxs)',

  'prepare-refill'    => 'Preparing Order (Refill)',
  'prepare-erx'       => 'Preparing Order (eScript)',
  'prepare-fax'       => 'Preparing Order (Fax)',
  'prepare-transfer'  => 'Preparing Order (Transfer)',
  'prepare-phone'     => 'Preparing Order (Phone)',
  'prepare-mail'      => 'Preparing Order (Mail)',

  'shipped-mail-pay' => 'Shipped (Pay by Mail)',
  'shipped-auto-pay' => 'Shipped (Autopay Scheduled)',
  'shipped-web-pay'  => 'Shipped (Pay Online)',
  'shipped-part-pay' => 'Shipped (Partially Paid)',

  'done-card-pay'    => 'Completed (Paid by Card)',
  'done-mail-pay'    => 'Completed (Paid by Mail)',
  'done-finaid'      => 'Completed (Financial Aid)',
  'done-fee-waived'  => 'Completed (Fee Waived)',
  'done-clinic-pay'  => 'Completed (Paid by Clinic)',
  'done-auto-pay'    => 'Completed (Paid by Autopay)',
  'done-refused-pay' => 'Completed (Refused to Pay)',

  'late-mail-pay'     => 'Shipped (Mail Payment Not Made)',
  'late-card-missing' => 'Shipped (Autopay Card Missing)',
  'late-card-expired' => 'Shipped (Autopay Card Expired)',
  'late-card-failed'  => 'Shipped (Autopay Card Failed)',
  'late-web-pay'      => 'Shipped (Online Payment Not Made)',
  'late-payment-plan' => 'Shipped (Payment Plan Approved)',

  'return-usps'      => 'Returned (USPS)',
  'return-customer'  => 'Returned (Customer)'
];

const STOCK_LEVEL = [
  'HIGH SUPPLY'  => 'HIGH SUPPLY',
  'LOW SUPPLY'   => 'LOW SUPPLY',
  'ONE TIME'     => 'ONE TIME',
  'REFILL ONLY'  => 'REFILL ONLY',
  'OUT OF STOCK' => 'OUT OF STOCK',
  'NOT OFFERED'  => 'NOT OFFERED'
];

const RX_MESSAGE = [
  'NO ACTION STANDARD FILL' => [
    'EN' => '',
    'ES' => ''
  ],
  'NO ACTION PAST DUE AND SYNC TO ORDER' => [
    'EN' => 'is past due so synced to Order **',
    'ES' => ''
  ],
  'NO ACTION DUE SOON AND SYNC TO ORDER' => [
    'EN' => 'is due soon so synced to Order **',
    'ES' => ''
  ],
  'NO ACTION NEW RX SYNCED TO ORDER' => [
    'EN' => 'is a new Rx synced to Order **',
    'ES' => ''
  ],
  'NO ACTION SYNC TO DATE' => [
    'EN' => 'was synced to refill_target_date',
    'ES' => ''
  ],
  'NO ACTION RX OFF AUTOFILL' => [
    'EN' => 'was requested',
    'ES' => ''
  ],
  'NO ACTION RECENT FILL' => [
    'EN' => 'filled on refill_date_last and due on refill_date_next',
    'ES' => ''
  ],
  'NO ACTION NOT DUE' => [
    'EN' => 'is not due until refill_date_next',
    'ES' => ''
  ],
  'NO ACTION CHECK SIG' => [
    'EN' => 'was prescribed in an unusually high qty and needs to be reviewed by a pharmacist',
    'ES' => ''
  ],
  'NO ACTION MISSING GSN' => [
    'EN' => "is being checked if it's available",
    'ES' => ''
  ],
  'NO ACTION NEW GSN' => [
    'EN' => 'is being verified',
    'ES' => ''
  ],
  'NO ACTION LOW STOCK' => [
    'EN' => 'is low in stock',
    'ES' => ''
  ],
  'NO ACTION LOW REFILL' => [
    'EN' => 'has limited refills',
    'ES' => ''
  ],
  'NO ACTION WILL TRANSFER CHECK BACK' => [
    'EN' => 'will be transferred to your local pharmacy. Check back in 3 months',
    'ES' => ''
  ],
  'NO ACTION WILL TRANSFER' => [
    'EN' => 'is not offered and will be transferred to your local pharmacy',
    'ES' => ''
  ],
  'NO ACTION WAS TRANSFERRED' => [
    'EN' => 'was transferred out to your local pharmacy on rx_date_changed',
    'ES' => ''
  ],

  //ACTION BY USER REQUIRED BEFORE (RE)FILL

  'ACTION EXPIRING' => [
    'EN' => 'will expire soon, ask your doctor for a new Rx',
    'ES' => ''
  ],
  'ACTION LAST REFILL' => [
    'EN' => 'is the last refill, contact your doctor',
    'ES' => ''
  ],
  'ACTION NO REFILLS' => [
    'EN' => 'is out of refills, contact your doctor',
    'ES' => ''
  ],
  'ACTION EXPIRED' => [
    'EN' => 'has expired, ask your doctor for a new Rx',
    'ES' => ''
  ],
  'ACTION EXPIRING' => [
    'EN' => 'will expire soon, ask your doctor for a new Rx',
    'ES' => ''
  ],
  'ACTION CHECK BACK' => [
    'EN' => 'is unavailable for new RXs at this time, check back later',
    'ES' => ''
  ],
  'ACTION RX OFF AUTOFILL' => [
    'EN' => 'has autorefill turned off, request 2 weeks in advance',
    'ES' => ''
  ],
  'ACTION PATIENT OFF AUTOFILL' => [
    'EN' => 'was not filled because you have turned all medications off autorefill',
    'ES' => ''
  ],
  'ACTION NEEDS FORM' => [
    'EN' => 'can be filled once you register',
    'ES' => ''
  ]
];
