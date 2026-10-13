<?php

declare(strict_types=1);

return [
    'currency' => 'сум',

    // Регистрация
    'choose_lang' => "🇺🇿 Tilni tanlang\n🇷🇺 Выберите язык",
    'lang.uz' => "🇺🇿 O'zbekcha",
    'lang.ru' => '🇷🇺 Русский',
    'lang_saved' => '✅ Язык изменён',
    'ask_phone' => '📱 Отправьте свой номер телефона — нажмите кнопку ниже.',
    'phone_own_only' => 'Пожалуйста, отправьте <b>свой</b> номер с помощью кнопки ниже.',
    'welcome' => "Добро пожаловать, {name}! 🦁\n<b>Lion Lavash</b> — вкусные бургеры и лаваши.",
    'main_menu' => 'Выберите раздел 👇',
    'use_buttons' => 'Пожалуйста, используйте кнопки ниже 👇',

    // Кнопки
    'btn.send_phone' => '📱 Отправить номер',
    'btn.menu' => '🍔 Меню',
    'btn.cart' => '🛒 Корзина',
    'btn.orders' => '📦 Мои заказы',
    'btn.contact' => '☎️ Контакты',
    'btn.lang' => '🌐 Язык',
    'btn.cancel' => '❌ Отмена',
    'btn.skip' => '➡️ Пропустить',
    'btn.send_location' => '📍 Отправить локацию',
    'btn.cash' => '💵 Наличными',
    'btn.card' => '💳 Картой',
    'btn.confirm' => '✅ Подтвердить',
    'btn.back' => '⬅️ Назад',
    'btn.add_to_cart' => '🛒 В корзину',
    'btn.checkout' => '✅ Оформить заказ',
    'btn.clear_cart' => '🧹 Очистить',
    'btn.send_receipt' => '📎 Отправить чек #{id}',

    // Меню
    'categories' => '🍔 Выберите категорию:',
    'no_categories' => 'Меню пока пустое.',
    'products' => "<b>{category}</b>\nВыберите блюдо:",
    'no_products' => 'В этой категории пока нет блюд.',
    'product_card' => "<b>{name}</b>\n{description}\n💰 Цена: {price}\n🔢 Количество: {qty} шт.\n💵 Итого: {total}",
    'added_to_cart' => '✅ Добавлено в корзину ({qty} шт.)',

    // Корзина
    'cart_empty' => '🛒 Ваша корзина пуста.',
    'cart_title' => '🛒 <b>Корзина</b>',
    'cart_line' => "<b>{name}</b>\n    {qty} × {price} = {total}",
    'cart_items_total' => 'Товары: {amount}',
    'cart_delivery' => 'Доставка: {amount}',
    'cart_delivery_free' => 'Доставка: бесплатно ✅',
    'cart_free_hint' => '💡 Бесплатная доставка от {amount}',
    'cart_total' => '<b>Итого: {amount}</b>',
    'cart_cleared' => 'Корзина очищена',

    // Оформление
    'closed' => "⏰ Сейчас заказы не принимаются.\nМы работаем: <b>{hours}</b>",
    'ask_address' => '📍 Отправьте адрес доставки: нажмите кнопку локации или напишите адрес.',
    'address_invalid' => 'Адрес должен содержать от 3 до 255 символов. Попробуйте ещё раз.',
    'ask_comment' => '💬 Оставьте комментарий (подъезд, этаж, домофон) или пропустите.',
    'comment_invalid' => 'Не более 500 символов. Пожалуйста, короче.',
    'ask_payment' => '💳 Выберите способ оплаты:',
    'confirm_title' => '📋 <b>Подтвердите заказ</b>',
    'confirm_address' => '📍 Адрес: {address}',
    'address_location' => 'локация',
    'confirm_comment' => '💬 Комментарий: {comment}',
    'confirm_payment' => '💳 Оплата: {method}',
    'confirm_phone' => '📱 Телефон: {phone}',
    'confirm_hint' => 'Всё верно? Нажмите «✅ Подтвердить».',
    'payment.cash' => 'Наличными (курьеру)',
    'payment.card' => 'Перевод на карту',
    'checkout_cancelled' => 'Оформление заказа отменено.',
    'order_created_cash' => "✅ Заказ <b>#{id}</b> принят!\nИтого: <b>{total}</b>\n\nОператор скоро подтвердит его. Мы сообщим об изменении статуса.",
    'order_created_card' => '✅ Заказ <b>#{id}</b> создан!',

    // Оплата
    'receipt_request' => "💳 Переведите <b>{total}</b> за заказ <b>#{id}</b> на карту:\n\n<code>{card}</code>\n👤 {owner}\n\nЗатем отправьте сюда фото (скриншот) чека.",
    'receipt_expected' => '📎 Пожалуйста, отправьте фото чека об оплате.',
    'receipt_received' => '✅ Чек получен. Сообщим, как только оплата будет проверена.',

    // Мои заказы
    'orders_empty' => 'У вас пока нет заказов.',
    'orders_title' => '📦 <b>Ваши последние заказы</b>',
    'order_line' => "<b>#{id}</b> · {date} · {total}\n{status}",

    // Контакты
    'contact_info' => "☎️ Телефон: {phone}\n⏰ Время работы: {hours}\n🚚 Только доставка",

    // Статусы
    'status.new' => '🆕 Новый — ожидает подтверждения',
    'status.awaiting_payment' => '💳 Ожидает оплаты',
    'status.payment_review' => '🔎 Оплата проверяется',
    'status.accepted' => '✅ Принят',
    'status.preparing' => '👨‍🍳 Готовится',
    'status.ready' => '📦 Готов, передаётся курьеру',
    'status.on_the_way' => '🚚 В пути',
    'status.delivered' => '🎉 Доставлен',
    'status.cancelled' => '❌ Отменён',

    // Уведомления
    'notify.status' => '📦 Заказ <b>#{id}</b>: {status}',
    'notify.payment_rejected' => "❌ Оплата заказа <b>#{id}</b> не подтверждена.\nОтправьте чек повторно или свяжитесь с нами: {phone}",

    // Ошибки
    'error.generic' => '⚠️ Произошла ошибка. Пожалуйста, попробуйте чуть позже.',
    'error.cart_empty' => '🛒 Ваша корзина пуста.',
    'error.product_unavailable' => 'Это блюдо сейчас недоступно.',
    'error.order_not_found' => 'Заказ не найден.',
    'error.receipt_not_expected' => 'Для этого заказа чек не ожидается.',
    'error.address_required' => 'Не указан адрес.',
    'error.phone_required' => 'Не указан номер телефона.',
    'error.invalid_payment_method' => 'Неверный способ оплаты.',
    'error.invalid_transition' => 'Это действие недоступно.',
];
