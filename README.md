wordpress-futupayments-shortcode
================================
Простой модуль для приёма оплаты с пластиковых карт через Futubank.com. Близкий аналог http://wordpress.org/plugins/robokassa-shortcode/installation/


Установка на сервер
===================

1. Скачайте и распакуйте архив:

```
https://github.com/Futubank/wordpress-futupayments-shortcode/archive/master.zip```
```

2. Скопируйте каталог futupayments-shortcode из архива в каталог `wp-content/plugins` вашего сайта

3. В администраторской панели вашего сайта откройте раздел «Плагины», найдите «Futupayments Shortcode» и нажмите «Активировать»:

![Активация плагина](http://futubank.github.io/futuplugins/static/wp/install.png)

Настройки в личном кабинете Futubank.com
========================================

Зайдите в личный кабинет http://secure.futubank.com, выберите свой магазин (или создайте новый) и перейдите в раздел «Уведомления». Отметьте пункт «Уведомления с помощью POST-запросов» / «на выбранную страницу вашего сайта» и укажите там ссылку

    http://вашсайт/?futupayment-callback

![Уведомления](http://futubank.github.io/futuplugins/static/wp/trans.png)

Настройки на вашем сайте
========================

В администраторской панели вашего сайта откройте раздел

    Настройки → Futupayments Shortcode

![Активация плагина](http://futubank.github.io/futuplugins/static/wp/settings.png)

Заполните поля «Merchant ID» и «Secret key». Эти значения уникальны для каждого магазина, посмотреть их можно в личном кабинете Futubank в разделе «Готовые модули».

Сохраните изменения.

Использование
=============
Чтобы в тексте страницы или поста появилась кнопка для оплаты услуги на фиксированную сумму:
```
[futupayment amount="10.99" currency="RUB" description="Описание платежа"]
```

То же самое, но запрашивается имя, email и телефон клиента:

```
[futupayment amount="10.99" currency="RUB" description="Описание платежа" fields="client_name,client_email,client_phone"]
```

Форма приёма произвольной суммы с запросом имени и телефона:
```
[futupayment currency="RUB" description="Описание платежа" fields="client_name,client_phone"]
```
