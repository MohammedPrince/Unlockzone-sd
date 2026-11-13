@extends(auth()->check() ? 'layouts.panel' : 'layouts.public')

{{-- Terms of Service. The text follows the language picked in the top bar. Extra terms from Admin > Settings are added at the end. --}}
@php
    $ar = app()->getLocale() === 'ar';
    $site = setting('site_name');
    $updated = $ar ? '14 نوفمبر 2026' : 'November 14, 2026';

    $intro = $ar
        ? "مرحباً بك في {$site}. باستخدامك للموقع أو إنشاء حساب أو تقديم أي طلب، فإنك توافق على هذه الشروط. يرجى قراءتها بعناية."
        : "Welcome to {$site}. By using this website, creating an account or placing any order, you agree to these terms. Please read them carefully.";

    $sections = $ar ? [
        ['account', 'الحساب', [
            'يجب أن تكون البيانات التي تسجّل بها صحيحة وحديثة، وأن يكون الحساب لشخص واحد أو جهة واحدة.',
            'أنت مسؤول عن الحفاظ على سرية كلمة المرور وعن كل ما يتم من خلال حسابك.',
            'قد تحتاج الحسابات الجديدة إلى موافقة الإدارة قبل استخدامها.',
        ]],
        ['credit', 'الرصيد المسبق', [
            'تُدفع جميع الخدمات من <b>رصيد مسبق الدفع</b> في حسابك، ويُضاف الرصيد من قبل الإدارة بعد تأكيد الدفع.',
            'الرصيد مخصص لشراء الخدمات على هذا الموقع فقط، <b>ولا يمكن سحبه نقداً</b> أو تحويله إلى حساب آخر إلا بموافقة الإدارة.',
            'الأسعار معروضة بالدولار وقد تتغير في أي وقت؛ ويُطبَّق السعر الظاهر لحظة تقديم الطلب.',
        ]],
        ['orders', 'الطلبات', [
            'أنت مسؤول عن صحة البيانات التي تدخلها (مثل IMEI أو الرقم التسلسلي أو البريد). <b>راجعها جيداً قبل الإرسال</b>.',
            'يبدأ تنفيذ الطلب فور تقديمه ويُخصم السعر من رصيدك مباشرة، <b>لذلك لا يمكن إلغاء الطلب بعد إرساله</b>.',
            'مدة التسليم المعروضة تقديرية، وقد تختلف حسب المزوّد أو ضغط العمل.',
            'تظهر نتيجة الطلب (الكود أو الرد) في صفحة الطلب وفي سجل الطلبات.',
        ]],
        ['refunds', 'الاسترداد', [
            'إذا <b>فشل</b> الطلب أو ألغته الإدارة، يُعاد المبلغ كاملاً إلى رصيدك تلقائياً.',
            'لا يُسترد المبلغ إذا اكتمل الطلب، أو إذا فشل بسبب بيانات خاطئة أدخلتها، أو لأن الجهاز لا يدعم الخدمة كما هو موضح في وصفها.',
            'يتم الاسترداد كرصيد في حسابك وليس نقداً.',
        ]],
        ['use', 'الاستخدام المسموح', [
            'تُستخدم الخدمات فقط على أجهزة تملكها أو لديك تصريح من مالكها بالعمل عليها.',
            '<b>يُمنع</b> استخدام الخدمات على أجهزة مسروقة أو مفقودة أو بغرض الاحتيال أو أي نشاط مخالف للقانون.',
            'يحق لنا رفض أي طلب أو إيقاف أي حساب عند الاشتباه في إساءة الاستخدام، مع إعادة رصيد الطلبات غير المنفّذة.',
        ]],
        ['third', 'خدمات الأطراف الأخرى', [
            'تُنفَّذ بعض الخدمات عن طريق مزوّدين خارجيين، ولا نتحكم في توفرها أو مدتها بشكل كامل.',
            'أسماء الماركات والعلامات التجارية المذكورة ملك لأصحابها، ولسنا تابعين لها أو معتمدين منها.',
        ]],
        ['liability', 'حدود المسؤولية', [
            'تُقدَّم الخدمات «كما هي» دون ضمان لنتيجة معينة على كل جهاز أو لعمل الموقع دون انقطاع.',
            'في جميع الأحوال، لا تتجاوز مسؤوليتنا قيمة الطلب الذي دفعته.',
            'لسنا مسؤولين عن أي فقدان للبيانات أو ضرر في الجهاز ناتج عن استخدام الخدمة أو عن خطوات يقوم بها المستخدم.',
        ]],
        ['suspension', 'إيقاف الحساب', [
            'يحق للإدارة إيقاف الحساب أو منعه من طلب خدمات معينة عند مخالفة هذه الشروط.',
            'يمكنك طلب إغلاق حسابك في أي وقت عبر التواصل معنا.',
        ]],
        ['changes', 'تعديل الشروط', [
            'قد نحدّث هذه الشروط من وقت لآخر، ويظهر تاريخ آخر تحديث أعلى الصفحة. استمرارك في استخدام الموقع يعني موافقتك على النسخة المحدّثة.',
        ]],
    ] : [
        ['account', 'Your account', [
            'The details you register with must be true and up to date, and each account is for one person or business.',
            'You are responsible for keeping your password secret and for everything done through your account.',
            'New accounts may need approval by the administrator before they can be used.',
        ]],
        ['credit', 'Prepaid credit', [
            'All services are paid from a <b>prepaid balance</b> in your account. Credit is added by the administrator after your payment is confirmed.',
            'Credit can only be used to buy services on this website. <b>It cannot be withdrawn as cash</b> or moved to another account without the administrator\'s approval.',
            'Prices are shown in US dollars and may change at any time; the price shown when you place an order is the one charged.',
        ]],
        ['orders', 'Orders', [
            'You are responsible for the details you enter (such as IMEI, serial number or email). <b>Check them carefully before you submit.</b>',
            'Work on an order starts as soon as it is placed and the price is taken from your balance at once, <b>so an order cannot be cancelled after it is sent</b>.',
            'Delivery times are estimates and may vary with the provider or workload.',
            'The result of an order (code or reply) is shown on the order page and in your order history.',
        ]],
        ['refunds', 'Refunds', [
            'If an order <b>fails</b> or is cancelled by the administrator, the full amount is returned to your balance automatically.',
            'There is no refund when an order has been completed, when it fails because of wrong details you entered, or when your device is not supported as stated in the service description.',
            'Refunds are made as credit to your account, not as cash.',
        ]],
        ['use', 'Acceptable use', [
            'Use the services only on devices you own or are authorised by the owner to work on.',
            'Using the services on <b>stolen or lost devices</b>, for fraud or for any illegal activity is <b>forbidden</b>.',
            'We may refuse any order or suspend any account we suspect of misuse; credit for orders that were not carried out is returned.',
        ]],
        ['third', 'Third-party services', [
            'Some services are carried out by outside providers, so their availability and timing are not fully under our control.',
            'Brand names and trademarks mentioned on this site belong to their owners. We are not affiliated with or endorsed by them.',
        ]],
        ['liability', 'Limitation of liability', [
            'Services are provided "as is", without a guarantee of a particular result on every device or of uninterrupted availability of the site.',
            'In any case, our liability is limited to the amount you paid for the order concerned.',
            'We are not responsible for data loss or device damage caused by using a service or by steps taken by the user.',
        ]],
        ['suspension', 'Suspension', [
            'The administrator may suspend an account, or block it from certain services, if these terms are broken.',
            'You can ask us to close your account at any time by contacting us.',
        ]],
        ['changes', 'Changes to these terms', [
            'We may update these terms from time to time; the date of the last update is shown at the top. Continuing to use the site means you accept the updated version.',
        ]],
    ];
@endphp

@section('title', $ar ? 'شروط الخدمة' : 'Terms of Service')
@section('page-title', $ar ? 'شروط الخدمة' : 'Terms of Service')
@section('eyebrow', $ar ? 'قانوني' : 'Legal')

@section('content')
    @include('partials.legal-body', ['extra' => setting('terms_text')])
@endsection
