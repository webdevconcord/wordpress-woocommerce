# Модуль ConcordPay для WordPress WooCommerce

Creator: [ConcordPay](https://concordpay.concord.ua)<br>
Tags: WordPress, ConcordPay, payment, payment gateway, credit card, Visa, Masterсard, Apple Pay, Google Pay<br>
Requires at least: WordPress 5.5, WooCommerce 6.0<br>
License: GNU GPL v3.0<br>
License URI: [License](https://opensource.org/licenses/GPL-3.0)

Для работы модуля у вас должен быть установлен плагин **WooCommerce 6+**.

## Установка

1. Содержимое архива поместите в папку плагинов **WordPress**. Должна получиться следующая структура каталогов:<br>
   `wp-content/plugins/concordpay-for-woocommerce/{файлы плагина}`

2. Зайдите в админ раздел сайта `/wp-admin/` и активируйте плагин **ConcordPay для WooCommerce**.

3. Перейдите в раздел *«WooCommerce -> Settings -> Checkout»* (в новых версиях: *«WooCommerce -> Settings -> Payments»*).

4. В разделе *«Payment Gateways»* найдите и включите плагин **ConcordPay**, нажмите кнопку *«Manage»*.

5. Введите данные вашего торговца из личного кабинета **ConcordPay**.
   - *Идентификатор торговца (Merchant ID)*;
   - *Секретный ключ (Secret key)*.

*Модуль протестирован для работы с WordPress 6.1, WooCommerce 7.1 и PHP 8.1.*
