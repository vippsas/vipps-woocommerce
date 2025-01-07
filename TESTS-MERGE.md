LP Testing 06.01.2025
- [x] Normal payment functionality
    - [x] Woocommerce checkout 
    - [x] vipps express checkout
    - [x] vipps checkout. 
        - [x] vipps
            -  Works, but log shows error: "2024-01-06T11:24:06+00:00 ERROR Kunne ikke sende bilde til Vipps -  413 Request Entity Too Large"

        - [ ] Card - I don't have WooPayments configured

        - [x] klarna integration - redirected to correct uri /checkout/order-pay.


    - [x] settings are working
        - [x] general
        - [x] express checkout 
            - not sure if "opprett nye kunder [...]" works. I do not get a  login screen, and the order shows john doe (vipps test profile) regardless of the setting.

        - [x] checkout 
            - not sure if "opprett nye kunder [...]" works. I do not get a login screen, and the order shows "anonym kunde" regardless of the setting.
            
        - [x] advanced
        - [x] dev
        - [x] badges
        - [x] webooks
        - [x] qr-code
- [ ] Normal recurring functionality
    - I don't have recurring keys

- [x] Is recurring deactivated when subscriptions is deactivated?
- [x] Is recurring activated when subscriptions is activated?
    - works, but error from site log: [07-Jan-2025 10:04:33 UTC] Feil ved gjenplanlegging av hendelsen for knaggen: wooc
ommerce_vipps_recurring_check_order_statuses, feilkode: invalid_schedule, feilmeld
ing: Hendelsplanen finnes ikke., data: {"schedule":"one_minute","args":[],"interva
l":60}                                                                            
[07-Jan-2025 10:04:33 UTC] Feil ved gjenplanlegging av hendelsen for knaggen: wooc
ommerce_vipps_recurring_check_gateway_change_request, feilkode: invalid_schedule, 
feilmelding: Hendelsplanen finnes ikke., data: {"schedule":"one_minute","args":[],
"interval":60}                                                                    
[07-Jan-2025 10:04:33 UTC] Feil ved gjenplanlegging av hendelsen for knaggen: woocommerce_vipps_recurring_update_subscription_details_in_app, feilkode: invalid_schedule, feilmelding: Hendelsplanen finnes ikke., data: {"schedule":"one_minute","args":[],"interval":60} 

- [x] Is recurring activated when standalone is deactivated?
- [x] Recurring standalone warning before deactivation
- [x] Recurring standalone warning/error on activation
    - Warning ok. On activation attempt no error is shown, page just reloads.

- [x] Fresh wp install woo-vipps -> recurring standalone
    - XXX I was able to activate recurring again and again, even though the warning is there. No big deal as woo-vipps will not load recurring again, but not intended
    