<?php

namespace RealexPayments\HPP\Model\Config\Source;

class DMFields implements \Magento\Framework\Option\ArrayInterface
{
    const DM_BILL_STR1 = 'HPP_BILLING_STREET1';
    const DM_BILL_STR2 = 'HPP_BILLING_STREET2';
    const DM_BILL_CITY = 'HPP_BILLING_CITY';
    const DM_BILL_POSTAL = 'HPP_BILLING_POSTALCODE';
    const DM_BILL_STATE = 'HPP_BILLING_STATE';
    const DM_BILL_COUNTRY = 'BILLING_CO';

    const DM_SHIPPING_FIRST = 'HPP_SHIPPING_FIRSTNAME';
    const DM_SHIPPING_LAST = 'HPP_SHIPPING_LASTNAME';
    const DM_SHIPPING_PHONE = 'HPP_SHIPPING_PHONE';
    const DM_SHIPPING_METHOD = 'HPP_SHIPPING_SHIPPINGMETHOD';
    const DM_SHIPPING_STR1 = 'HPP_SHIPPING_STREET1';
    const DM_SHIPPING_STR2 = 'HPP_SHIPPING_STREET2';
    const DM_SHIPPING_CITY = 'HPP_SHIPPING_CITY';
    const DM_SHIPPING_POSTAL = 'HPP_SHIPPING_POSTALCODE';
    const DM_SHIPPING_STATE = 'HPP_SHIPPING_STATE';
    const DM_SHIPPING_COUNTRY = 'SHIPPING_CO';

    const DM_CUSTOMER_ID = 'HPP_CUSTOMER_ID';
    const DM_CUSTOMER_DOB = 'HPP_CUSTOMER_DATEOFBIRTH';
    const DM_CUSTOMER_EMAIL_DOMAIN = 'HPP_CUSTOMER_DOMAINNAME';
    const DM_CUSTOMER_EMAIL = 'HPP_CUSTOMER_EMAIL';
    const DM_CUSTOMER_FIRST = 'HPP_CUSTOMER_FIRSTNAME';
    const DM_CUSTOMER_LAST = 'HPP_CUSTOMER_LASTNAME';
    const DM_CUSTOMER_PHONE = 'HPP_CUSTOMER_PHONENUMBER';

    const DM_PRODUCTS_TOTAL = 'HPP_PRODUCTS_UNITPRICE';

    const DM_FRAUD_HOST = 'HPP_FRAUD_DM_BILLHOSTNAME';
    const DM_FRAUD_COOKIES = 'HPP_FRAUD_DM_BILLHTTPBROWSERCOOKIESACCEPTED';
    const DM_FRAUD_BROWSER = 'HPP_FRAUD_DM_BILLTOHTTPBROWSERTYPE';
    const DM_FRAUD_IP = 'HPP_FRAUD_DM_BILLTOIPNETWORKADDRESS';
    const DM_FRAUD_TENDER = 'HPP_FRAUD_DM_INVOICEHEADERTENDERTYPE';

    /**
     * Possible Decision Manager fields.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
          [
              'value' => self::DM_BILL_STR1,
              'label' => 'HPP_BILLING_STREET1',
          ],
          [
              'value' => self::DM_BILL_STR2,
              'label' => 'HPP_BILLING_STREET2',
          ],
          [
              'value' => self::DM_BILL_CITY,
              'label' => 'HPP_BILLING_CITY',
          ],
          [
              'value' => self::DM_BILL_POSTAL,
              'label' => 'HPP_BILLING_POSTALCODE',
          ],
          [
              'value' => self::DM_BILL_STATE,
              'label' => 'HPP_BILLING_STATE',
          ],
          [
              'value' => self::DM_BILL_COUNTRY,
              'label' => 'BILLING_CO',
          ],
          [
              'value' => self::DM_SHIPPING_FIRST,
              'label' => 'HPP_SHIPPING_FIRSTNAME',
          ],
          [
              'value' => self::DM_SHIPPING_LAST,
              'label' => 'HPP_SHIPPING_LASTNAME',
          ],
          [
              'value' => self::DM_SHIPPING_PHONE,
              'label' => 'HPP_SHIPPING_PHONE',
          ],
          [
              'value' => self::DM_SHIPPING_METHOD,
              'label' => 'HPP_SHIPPING_SHIPPINGMETHOD',
          ],
          [
              'value' => self::DM_SHIPPING_STR1,
              'label' => 'HPP_SHIPPING_STREET1',
          ],
          [
              'value' => self::DM_SHIPPING_STR2,
              'label' => 'HPP_SHIPPING_STREET2',
          ],

          [
              'value' => self::DM_SHIPPING_CITY,
              'label' => 'HPP_SHIPPING_CITY',
          ],
          [
              'value' => self::DM_SHIPPING_POSTAL,
              'label' => 'HPP_SHIPPING_POSTALCODE',
          ],
          [
              'value' => self::DM_SHIPPING_STATE,
              'label' => 'HPP_SHIPPING_STATE',
          ],
          [
              'value' => self::DM_SHIPPING_COUNTRY,
              'label' => 'SHIPPING_CO',
          ],
          [
              'value' => self::DM_CUSTOMER_ID,
              'label' => 'HPP_CUSTOMER_ID',
          ],
          [
              'value' => self::DM_CUSTOMER_DOB,
              'label' => 'HPP_CUSTOMER_DATEOFBIRTH',
          ],
          [
              'value' => self::DM_CUSTOMER_EMAIL_DOMAIN,
              'label' => 'HPP_CUSTOMER_DOMAINNAME',
          ],
          [
              'value' => self::DM_CUSTOMER_EMAIL,
              'label' => 'HPP_CUSTOMER_EMAIL',
          ],
          [
              'value' => self::DM_CUSTOMER_FIRST,
              'label' => 'HPP_CUSTOMER_FIRSTNAME',
          ],
          [
              'value' => self::DM_CUSTOMER_LAST,
              'label' => 'HPP_CUSTOMER_LASTNAME',
          ],
          [
              'value' => self::DM_CUSTOMER_PHONE,
              'label' => 'HPP_CUSTOMER_PHONENUMBER',
          ],
          [
              'value' => self::DM_PRODUCTS_TOTAL,
              'label' => 'HPP_PRODUCTS_UNITPRICE',
          ],
          [
              'value' => self::DM_FRAUD_HOST,
              'label' => 'HPP_FRAUD_DM_BILLHOSTNAME',
          ],
          [
              'value' => self::DM_FRAUD_COOKIES,
              'label' => 'HPP_FRAUD_DM_BILLHTTPBROWSERCOOKIESACCEPTED',
          ],
          [
              'value' => self::DM_FRAUD_BROWSER,
              'label' => 'HPP_FRAUD_DM_BILLTOHTTPBROWSERTYPE',
          ],
          [
              'value' => self::DM_FRAUD_IP,
              'label' => 'HPP_FRAUD_DM_BILLTOIPNETWORKADDRESS',
          ],
          [
              'value' => self::DM_FRAUD_TENDER,
              'label' => 'HPP_FRAUD_DM_INVOICEHEADERTENDERTYPE',
          ],
        ];
    }
}
