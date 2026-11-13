@extends('layouts.panel')

{{--
    Customer manual. One page, two languages: the text follows the language picked in the top bar (EN / عربي).
    Each section: an id (for the contents list), a title and a list of steps. <b> is allowed in steps.
--}}
@php
    $ar = app()->getLocale() === 'ar';
    $site = setting('site_name');
    $whatsapp = setting('whatsapp_enabled') === '1' ? preg_replace('/[^0-9]+/', '', (string) setting('whatsapp_number')) : '';

    $sections = $ar ? [
        ['start', 'البداية', [
            'سجّل الدخول باسم المستخدم أو البريد الإلكتروني وكلمة المرور.',
            'اضغط زر <b>القائمة ☰</b> في الأعلى لفتح قائمة حسابك. من الكمبيوتر يمكنك إخفاء القائمة أو إظهارها بنفس الزر.',
            'غيّر اللغة من <b>EN / عربي</b>، والوضع الليلي أو النهاري من زر <b>الشمس / القمر</b>.',
            'رصيدك الحالي يظهر دائماً في <b>منطقة العميل</b> وفي أعلى القائمة.',
        ]],
        ['funds', 'إضافة رصيد', [
            'الخدمات تُدفع من رصيدك المسبق. لإضافة رصيد اضغط <b>+ إضافة رصيد</b> في القائمة أو <b>شراء رصيد</b> في لوحة العميل.',
            'سيفتح لك محادثة واتساب مع الإدارة. أرسل المبلغ الذي تريد شحنه وطريقة الدفع.',
            'بعد التأكيد يضيف المسؤول الرصيد إلى حسابك ويظهر فوراً، وتجده في <b>كشف حسابي</b> بعلامة <b>CR</b> الخضراء.',
        ]],
        ['order', 'تقديم طلب', [
            'من القائمة اختر <b>تقديم طلب</b> ثم القسم (مثلاً خدمات EMI)، أو <b>كل الخدمات</b>.',
            'كل خدمة تعرض سعرها بالأخضر. علامة <b>Instant</b> الصفراء تعني أن الخدمة تُنفَّذ فوراً.',
            'افتح الخدمة واملأ الحقول المطلوبة بدقة. رقم <b>IMEI</b> مكوّن من 15 رقماً، وتجده بالاتصال على <b>*#06#</b>.',
            'ضع علامة على <b>الموافقة على شروط الخدمة</b> ثم اضغط <b>تقديم الطلب</b> وأكّد.',
            'يُخصم السعر من رصيدك مباشرة، ويظهر لك قبل الطلب: السعر، رصيدك، والرصيد بعد الطلب.',
            'تستخدم خدمة كثيراً؟ اضغط <b>أريد استخدامها كثيراً</b> لتظهر أولاً في قائمة الخدمات.',
        ]],
        ['track', 'متابعة الطلبات', [
            'من <b>سجل الطلبات</b> افتح قسم الخدمة أو <b>طلبات السيرفر</b> لكل طلباتك.',
            'عمود <b>الكود</b> يعرض النتيجة (الكود أو الرد) عند اكتمال الطلب، وعمود <b>الحقول</b> يعرض ما أدخلته.',
            'استخدم <b>بحث</b> للبحث برقم الطلب أو اسم الخدمة، و<b>تصدير</b> لتنزيل طلباتك كملف Excel.',
        ]],
        ['status', 'معنى حالات الطلب', [
            '<b>قيد التنفيذ</b>: يبدأ العمل على طلبك فور تقديمه، لذلك لا يمكن إلغاؤه بعد الإرسال. تأكد من بياناتك قبل الطلب.',
            '<b>مكتمل</b>: تم التنفيذ. افتح الطلب لترى النتيجة.',
            '<b>فشل / ملغى</b>: أُغلق الطلب ويُعاد المبلغ إلى رصيدك تلقائياً.',
        ]],
        ['statement', 'كشف الحساب', [
            '<b>كشف حسابي</b> يعرض كل حركة على رصيدك مع التاريخ والوصف والمبلغ والرصيد بعدها.',
            '<b>CR</b> (أخضر) = رصيد دخل حسابك: شحن أو استرداد. <b>DR</b> (أحمر) = رصيد خرج: طلب جديد.',
            'اختر نوع الحركة ثم <b>بحث</b> للتصفية، و<b>تصدير إلى Excel</b> لتنزيل الكشف.',
        ]],
        ['safety', 'نصائح وأمان', [
            'لا تشارك كلمة المرور مع أحد. غيّرها من <b>ملفي الشخصي</b>.',
            'تحقّق من <b>آخر تسجيل دخول</b> في القائمة؛ إن رأيت وقتاً أو IP لا تعرفه غيّر كلمة المرور فوراً وتواصل معنا.',
            'راجع البيانات قبل الطلب: الطلب المكتمل لا يُسترد إذا كانت البيانات المدخلة خاطئة.',
        ]],
    ] : [
        ['start', 'Getting started', [
            'Sign in with your username or email and your password.',
            'Tap the <b>menu ☰</b> button at the top to open your account menu. On a computer the same button hides or shows the menu.',
            'Switch the language with <b>EN / عربي</b>, and dark or light mode with the <b>sun / moon</b> button.',
            'Your current balance is always shown in the <b>Client Area</b> and at the top of the menu.',
        ]],
        ['funds', 'Adding funds', [
            'Services are paid from your prepaid balance. To top it up, tap <b>+ Add Funds</b> in the menu or <b>Buy Credit</b> on your dashboard.',
            'A WhatsApp chat with us opens. Send the amount you want and how you will pay.',
            'Once confirmed, we add the credit to your account. It shows up right away, and you will find it in <b>My Statement</b> marked with a green <b>CR</b>.',
        ]],
        ['order', 'Placing an order', [
            'In the menu, under <b>Place an order</b>, pick a category (for example EMI Services) or <b>All services</b>.',
            'Each service shows its price in green. A yellow <b>Instant</b> tag means it is delivered immediately.',
            'Open the service and fill in the fields it asks for carefully. An <b>IMEI</b> is 15 digits: dial <b>*#06#</b> on the phone to see it.',
            'Tick <b>Agree with terms of services</b>, then tap <b>Place an order</b> and confirm.',
            'The price is taken from your balance straight away. Before you order you can see the price, your balance and your balance after the order.',
            'Use a service often? Tap <b>Want to use frequently</b> and it will be listed first on the services page.',
        ]],
        ['track', 'Tracking your orders', [
            'Under <b>Order history</b>, open a category or <b>Server Orders</b> for all your orders.',
            'The <b>Code</b> column shows the result (code or reply) when an order is completed; <b>Fields</b> shows what you entered.',
            'Use <b>Search</b> to find an order by reference or service name, and <b>Export</b> to download your orders as an Excel file.',
        ]],
        ['status', 'What the order statuses mean', [
            '<b>Processing</b>: work starts the moment you order, so an order cannot be cancelled once it is sent. Check your details before ordering.',
            '<b>Completed</b>: done. Open the order to see the result.',
            '<b>Failed / Cancelled</b>: the order was closed and the full amount is returned to your balance automatically.',
        ]],
        ['statement', 'Your statement', [
            '<b>My Statement</b> lists every change to your balance with the date, description, amount and the balance after it.',
            '<b>CR</b> (green) = money in: funds added or a refund. <b>DR</b> (red) = money out: a new order.',
            'Pick a type and tap <b>Search</b> to filter, or <b>Export to Excel</b> to download your statement.',
        ]],
        ['safety', 'Tips and safety', [
            'Never share your password. You can change it in <b>My Profile</b>.',
            'Check <b>Last Login</b> in the menu. If you see a time or IP address you do not recognise, change your password at once and contact us.',
            'Double-check what you type before ordering: a completed order cannot be refunded if the details you entered were wrong.',
        ]],
    ];
@endphp

@section('title', $ar ? 'دليل الاستخدام' : 'User Guide')
@section('page-title', $ar ? 'دليل الاستخدام' : 'User Guide')

@section('content')
    <div class="guide">
        <div class="card guide-intro">
            <div class="card-body">
                <h2>{{ $ar ? 'كيف تستخدم ' . $site : 'How to use ' . $site }}</h2>
                <p class="muted">{{ $ar ? 'كل ما تحتاجه من إضافة الرصيد إلى استلام نتيجة طلبك، خطوة بخطوة.' : 'Everything from adding funds to receiving your result, step by step.' }}</p>
                <nav class="guide-toc" aria-label="{{ $ar ? 'المحتويات' : 'Contents' }}">
                    @foreach ($sections as $i => [$id, $title])
                        <a href="#{{ $id }}"><span>{{ $i + 1 }}</span>{{ $title }}</a>
                    @endforeach
                </nav>
            </div>
        </div>

        @foreach ($sections as $i => [$id, $title, $steps])
            <section class="card guide-section" id="{{ $id }}">
                <div class="card-head"><h2><span class="guide-num">{{ $i + 1 }}</span> {{ $title }}</h2></div>
                <div class="card-body">
                    <ol class="guide-steps">
                        @foreach ($steps as $step)
                            <li>{!! $step !!}</li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endforeach

        <div class="card guide-help">
            <div class="card-body">
                <h2>{{ $ar ? 'تحتاج مساعدة؟' : 'Need help?' }}</h2>
                <p class="muted">{{ $ar ? 'فريقنا جاهز للرد على أسئلتك.' : 'Our team is ready to answer your questions.' }}</p>
                @if ($whatsapp !== '')
                    <a class="btn btn-contact wa" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">@include('partials.icon', ['name' => 'whatsapp']) {{ $ar ? 'تواصل عبر واتساب' : 'Chat on WhatsApp' }}</a>
                @endif
            </div>
        </div>
    </div>
@endsection
