<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Account deletion grace window
    |--------------------------------------------------------------------------
    |
    | When a customer deletes their account it is soft-deleted and enters a
    | recovery window. During the window the account can be restored (by the
    | owner logging in or verifying an OTP) and its email/mobile remain
    | reserved. Once the window passes, the daily `accounts:purge-expired`
    | command anonymises the record and releases its unique identifiers so
    | the email/mobile can be used to register again.
    |
    */

    'deletion_grace_days' => (int) env('ACCOUNT_DELETION_GRACE_DAYS', 30),

];
