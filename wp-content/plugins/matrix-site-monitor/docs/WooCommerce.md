Checkout errors
Payment gateway unavailable
Apple Pay broken
Google Pay broken
Stripe API issues
PayPal callback failures
Cart not updating
Coupons failing
Shipping calculation wrong
Tax incorrect
Cart fragments slowing site
Products not purchasable
Out-of-stock issues
Variable products broken
Images missing
Price mismatch
Failed orders
Duplicate orders
Emails not sending
Stock not reducing
Refund failures
Add to Cart button not working
Quantity selector broken
Product variations unavailable
Wrong price displayed
Coupon code rejected
Shipping cost incorrect
Checkout button disabled
Payment declined unexpectedly
Order confirmation not received
Stock shown incorrectly
Wishlist broken
Account page errors

# Coverage (check `woocommerce` — see Latest Results → WooCommerce)
# Only registered when WooCommerce is active. No real charges / no customer mail.
# Automated: add-to-cart, qty update, checkout/account HTTP, variations, prices,
#   product-page price vs WC price sample, stock status quirks, failed-order spike.
# Partial: Stripe/PayPal/Apple/Google config; coupon apply; shipping calc packages;
#   tax rates; place-order markup; wishlist plugin page; order emails enabled.
# Manual: cart-fragments perf; live refunds; live wallet declines
#   (filters msm_wc_cart_fragments_slow, msm_wc_refund_issue).
# Related: wc_pages, wc_cart_checkout, wc_coupon, wc_order_lifecycle,
#   wc_gateways, wc_failed_orders.
