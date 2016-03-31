<?php

class PurchaseRequest extends GatewayRequestMessage
{
}

class PurchasedResponse extends GatewayResponseMessage
{
}

class CompletePurchaseRequest extends GatewayRequestMessage
{
}

class PurchaseError extends GatewayErrorMessage
{
}

class PurchaseRedirectResponse extends GatewayRedirectResponseMessage
{
}

class CompletePurchaseError extends GatewayErrorMessage
{
}
