# PASHA Bank Laravel SDK

[![Packagist Version](https://img.shields.io/packagist/v/romanalisoy/pashabank-laravel-sdk)](https://packagist.org/packages/romanalisoy/pashabank-laravel-sdk)
[![Laravel](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net)
[![License](https://img.shields.io/packagist/l/romanalisoy/pashabank-laravel-sdk)](LICENSE)

A modern, type-safe Laravel SDK for the PASHA Bank CardSuite ECOMM
(3D-Secure acquiring) integration. Supports SMS / DMS payments, reversal,
refund, and the full recurring payments lifecycle.

## Features

- **First-class Laravel 12 & 13 support** (PHP 8.2+)
- **Fluent builders** for every bank command — no magic strings
- **mTLS / PKCS#12** handshake handled for you
- **Eloquent persistence** with overridable table names and model classes
- **Events** for every significant lifecycle transition
- **Ready-made callback route** or helper-only mode, your choice
- **Multi-merchant** out of the box (single-merchant stays simple)
- **Testable**: `PashaBank::fake()` + Pest / PHPUnit friendly

## Installation

```bash
composer require romanalisoy/pashabank-laravel-sdk
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag=pashabank-config
php artisan vendor:publish --tag=pashabank-migrations
php artisan migrate
```

## Configuration

Add to your `.env`:

```dotenv
PASHABANK_MERCHANT_ID=0001234
PASHABANK_TERMINAL_ID=TRM001

# Certificate issued by the bank (PKCS#12):
PASHABANK_CERT_TYPE=pkcs12
PASHABANK_CERT_PATH=/secure/certs/imakstore.0001234.p12
PASHABANK_CERT_PASSWORD=your-keystore-password
PASHABANK_CA_PATH=/secure/certs/PSroot.pem

# Callback behaviour — either 'redirect' (server-rendered app) or 'json'
# (SPA / mobile backend):
PASHABANK_CALLBACK_RESPONSE=redirect
PASHABANK_SUCCESS_URL=/payment/success
PASHABANK_FAILURE_URL=/payment/failure
```

### Converting JKS → PKCS#12 (if the bank issued a `.jks`)

```bash
keytool -importkeystore \
  -srckeystore imakstore.jks -destkeystore imakstore.p12 \
  -deststoretype PKCS12 -srcalias ima \
  -deststorepass yourpassword -destkeypass yourpassword
```

### Custom table names

If your company policy disallows third-party prefixes, override them in
`config/pashabank.php`:

```php
'persistence' => [
    'tables' => [
        'transactions' => 'payments',
        'recurring' => 'payment_subscriptions',
    ],
    'models' => [
        'transaction' => App\Models\Payment::class,
        'recurring' => App\Models\Subscription::class,
    ],
],
```

Your custom models must extend the SDK's base models (or re-implement the
same interface — the SDK only uses public methods).

## Usage

### Simple SMS payment

```php
use Romanalisoy\PashaBank\Facades\PashaBank;

public function checkout(Order $order)
{
    $registration = PashaBank::sms()
        ->amount($order->total)          // 19.80 decimal
        ->currency('AZN')                // or '944'
        ->description("Order #{$order->id}")
        ->language(app()->getLocale())
        ->for($order)                    // polymorphic link
        ->register();

    return redirect($registration->redirectUrl);
}
```

After 3DS and card entry the bank POSTs back to `/pashabank/callback`,
which runs `command=c`, updates the transaction, fires
`PaymentCompleted` / `PaymentFailed`, and redirects the browser.

### Listening for completion

```php
use Romanalisoy\PashaBank\Events\PaymentCompleted;

Event::listen(PaymentCompleted::class, function (PaymentCompleted $e) {
    $order = $e->transaction->payable;
    $order->markPaid();
});
```

### DMS (auth + capture)

```php
$auth = PashaBank::dms()
    ->amount($order->total)
    ->currency('AZN')
    ->for($order)
    ->authorize();

return redirect($auth->redirectUrl);

// ...once the customer authorises and you are ready to capture:
PashaBank::dmsComplete($auth->transactionId)
    ->amount($order->total)
    ->currency('AZN')
    ->execute();
```

### Reversal and refund

```php
PashaBank::reversal($transId)->execute();                          // full reversal
PashaBank::reversal($transId)->amount(10.00)->execute();           // partial reversal
PashaBank::reversal($transId)->suspectedFraud()->execute();        // fraud flagged

PashaBank::refund($transId)->execute();                            // full refund
PashaBank::refund($transId)->amount(5.00)->execute();              // partial refund
```

### Recurring payments

```php
// 1. Register template + first charge (customer completes 3DS):
$registration = PashaBank::recurring()
    ->registerWithFirstPayment()
    ->amount(9.99)->currency('AZN')
    ->billerClientId('sub-'.$user->id)
    ->expiry('1231')               // MMYY
    ->description('Monthly subscription')
    ->for($user)
    ->register();

return redirect($registration->redirectUrl);

// 2. Charge later (no customer interaction needed):
PashaBank::recurring()
    ->execute('sub-'.$user->id)
    ->amount(9.99)->currency('AZN')
    ->charge();

// 3. Cancel:
PashaBank::recurring()->delete('sub-'.$user->id)->execute();
```

### Multi-merchant

```php
PashaBank::merchant('shop_two')->sms()->amount(50)->register();
```

Add further merchants to `config/pashabank.php`:

```php
'merchants' => [
    'main'     => [ /* ... */ ],
    'shop_two' => [ /* ... */ ],
],
```

### Manual completion (helper-only flow)

If you disable the built-in callback route, wire up your own endpoint:

```php
// config/pashabank.php
'callback' => ['enabled' => false, /* ... */],
```

```php
Route::post('/my/return', function (Request $request) {
    $status = PashaBank::completion($request->input('trans_id'))->get();

    return $status->isSuccessful()
        ? redirect('/thank-you')
        : redirect('/sorry');
});
```

## Testing

The SDK ships with a test-friendly fake that keeps every operation
functional (validation, events, persistence) while stubbing out the HTTP
call to the bank.

```php
use Romanalisoy\PashaBank\Facades\PashaBank;

it('charges the customer', function () {
    $fake = PashaBank::fake()->willReturnForCommand('v', [
        'TRANSACTION_ID' => 'test-trans-1',
    ]);

    $this->post('/checkout', [...]);

    $fake->assertCommandSent('v', fn ($p) => $p['amount'] === '19.80');
});
```

Simulate the bank callback:

```php
PashaBank::fake()->fakeCallback('test-trans-1', [
    'RESULT' => 'OK',
    'RESULT_CODE' => '000',
]);

$this->post('/pashabank/callback', ['trans_id' => 'test-trans-1'])
     ->assertRedirect('/payment/success');
```

## Events

| Event                 | Fired when                                           |
| --------------------- | ---------------------------------------------------- |
| `PaymentRegistered`   | Bank accepts a new payment registration              |
| `PaymentCompleted`    | `command=c` reports success                          |
| `PaymentFailed`       | `command=c` reports failure / decline / timeout      |
| `PaymentReversed`     | Successful `command=r`                               |
| `PaymentRefunded`     | Successful `command=k`                               |
| `RecurringRegistered` | Recurring template stored at bank                    |
| `RecurringExecuted`   | `command=e` completed (success or decline)           |
| `RecurringDeleted`    | Recurring template removed from bank                 |

## Exceptions

All SDK exceptions extend `Romanalisoy\PashaBank\Exceptions\PashaBankException`:

- `ConfigurationException` — missing merchant config, unreadable cert
- `ConnectionException` — TLS / DNS / timeout; retry-safe
- `MerchantException` — bank rejected the request structurally
- `ValidationException` — bad amount / currency / biller_client_id, etc.

## Security

- Card numbers are masked before touching logs (`mask_card_numbers` config)
- Sensitive fields (`cvv2`, `pan`, `expiry`) are scrubbed from log output
- TLSv1.2 is enforced for every request to the bank
- Callback route supports an IP allowlist (CIDR-aware)

## Requirements

- PHP 8.2+
- Laravel 12.x or 13.x
- PHP extensions: `openssl`, `curl`
- A signed `.p12` certificate issued by PASHA Bank

## License

MIT. See [LICENSE](LICENSE).

---

Not affiliated with PASHA Bank OJSC. Integration details are based on
the bank's public integration specification.
