# Extension hooks

You can hook into the payment process with your custom extensions.

Here's a list of all hooks available to extensions.

### Payment

 - `onAuthorized` called when a payment was successfully authorized. You'll get the `ServiceResponse` as parameter.
 - `onCaptured` called when a payment was successfully captured. You'll get the `ServiceResponse` as parameter.
 - `onRefunded` called when a payment was successfully refunded. You'll get the `ServiceResponse` as parameter.
 - `onVoid` called when a payment was successfully voided. You'll get the `ServiceResponse` as parameter.
 - `updateCMSFields` standard SilverStripe hook to update CMS fields.

### AuthorizeService

 - `onBeforeAuthorize` called just before the `authorize` call is being made to the gateway. Passes the Gateway-Data (an array) as parameter, which allows you to modify the gateway data prior to being sent.
 - `onAfterAuthorize` called just after the Omnipay `authorize` call. Will pass the Omnipay request object as parameter.
 - `onAfterSendAuthorize` called after `send` has been called on the Omnipay request object. You'll get the request as first, and the omnipay response as second parameter.
 - `onBeforeCompleteAuthorize` called just before the `completeAuthorize` call is being made to the gateway. Passes the Gateway-Data (an array) as parameter, which allows you to modify the gateway data prior to being sent.
 - `onAfterCompleteAuthorize` called just after the Omnipay `completeAuthorize` call. Will pass the Omnipay request object as parameter.

### CaptureService

 - `onBeforeCapture` called just before the `capture` call is being made to the gateway. Passes the Gateway-Data (an array) as parameter, which allows you to modify the gateway data prior to being sent.
 - `onAfterCapture` called just after the Omnipay `capture` call. Will pass the Omnipay request object as parameter.
 - `onAfterSendCapture` called after `send` has been called on the Omnipay request object. You'll get the request as first, and the omnipay response as second parameter.

### PurchaseService

 - `onBeforePurchase` called just before the `purchase` call is being made to the gateway. Passes the Gateway-Data (an array) as parameter, which allows you to modify the gateway data prior to being sent.
 - `onAfterPurchase` called just after the Omnipay `purchase` call. Will pass the Omnipay request object as parameter.
 - `onAfterSendPurchase` called after `send` has been called on the Omnipay request object. You'll get the request as first, and the omnipay response as second parameter.
 - `onBeforeCompletePurchase` called just before the `completePurchase` call is being made to the gateway. Passes the Gateway-Data (an array) as parameter, which allows you to modify the gateway data prior to being sent.
 - `onAfterCompletePurchase` called just after the Omnipay `completePurchase` call. Will pass the Omnipay request object as parameter.

### CaptureService

 - `onBeforeRefund` called just before the `refund` call is being made to the gateway. Passes the Gateway-Data (an array) as parameter, which allows you to modify the gateway data prior to being sent.
 - `onAfterRefund` called just after the Omnipay `refund` call. Will pass the Omnipay request object as parameter.
 - `onAfterSendRefund` called after `send` has been called on the Omnipay request object. You'll get the request as first, and the omnipay response as second parameter.

### VoidService

 - `onBeforeVoid` called just before the `void` call is being made to the gateway. Passes the Gateway-Data (an array) as parameter, which allows you to modify the gateway data prior to being sent.
 - `onAfterVoid` called just after the Omnipay `void` call. Will pass the Omnipay request object as parameter.
 - `onAfterSendVoid` called after `send` has been called on the Omnipay request object. You'll get the request as first, and the omnipay response as second parameter.

### ServiceFactory

You can use extension hooks to override what services are being created for what intent.

If somebody does the following:

```php
$factory = ServiceFactory::create();
$service = $factory->getService($payment, ServiceFactory::INTENT_PAYMENT);
```

The constant `ServiceFactory::INTENT_PAYMENT` just translates to a string `"payment"`, which invokes the following
hook on any ServiceFactory extension: `createPaymentService`. The hook will get the `Payment` object as parameter and
should return a `PaymentService` instance (eg. a subclass).

Example code that might be in your extension:

```php
// This is just an example and already implemented in the default Factory, do not create an actual extension to do this.
public function createPaymentService(Payment $payment)
{
    if (GatewayInfo::shouldUseAuthorize($payment->Gateway)) {
        return AuthorizeService::create($payment);
    } else {
        return PurchaseService::create($payment);
    }
 }
```

Please be aware that you can't implement the same create-method in multiple extensions. If the factory encounters
several Extensions with the same method, it will raise an exception.
