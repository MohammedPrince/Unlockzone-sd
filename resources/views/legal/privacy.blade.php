@extends(auth()->check() ? 'layouts.panel' : 'layouts.public')

{{-- Privacy Policy. Describes what this platform actually stores and why. Follows the language picked in the top bar. --}}
@php
    $ar = app()->getLocale() === 'ar';
    $site = setting('site_name');
    $updated = $ar ? '14 نوفمبر 2026' : 'November 14, 2026';

    $intro = $ar
        ? "تشرح هذه الصفحة البيانات التي يجمعها {$site} عند استخدامك للموقع، ولماذا نجمعها، وكيف نحميها. نحن لا نبيع بياناتك لأي جهة."
        : "This page explains what data {$site} collects when you use the site, why we collect it and how we protect it. We never sell your data.";

    $sections = $ar ? [
        ['collect', 'البيانات التي نجمعها', [
            '<b>بيانات الحساب</b>: الاسم، اسم المستخدم، البريد الإلكتروني، رقم الجوال، والعنوان الذي تدخله عند التسجيل.',
            '<b>بيانات الطلبات</b>: ما تدخله لتنفيذ الخدمة مثل رقم IMEI أو الرقم التسلسلي أو البريد، ونتيجة الطلب.',
            '<b>سجل الرصيد</b>: كل عملية شحن أو خصم أو استرداد على حسابك.',
            '<b>بيانات تقنية</b>: عنوان IP ووقت تسجيل الدخول، لحماية حسابك وعرض «آخر تسجيل دخول» لك.',
        ]],
        ['use', 'كيف نستخدم بياناتك', [
            'لإنشاء حسابك وتنفيذ طلباتك وإظهار نتائجها لك.',
            'لإدارة رصيدك وكشف حسابك والرد على استفساراتك.',
            'لحماية الموقع واكتشاف إساءة الاستخدام ومحاولات الدخول غير المصرّح بها.',
        ]],
        ['share', 'مشاركة البيانات', [
            'عند تنفيذ طلب عبر مزوّد خارجي، نرسل له <b>فقط</b> البيانات اللازمة لذلك الطلب (مثل رقم IMEI)، ولا نرسل بيانات حسابك.',
            'لا نبيع بياناتك ولا نؤجرها ولا نشاركها لأغراض تسويقية.',
            'قد نكشف البيانات إذا طلبتها جهة رسمية وفق القانون.',
        ]],
        ['cookies', 'ملفات تعريف الارتباط والتخزين', [
            'نستخدم ملف جلسة ضروري لإبقائك مسجلاً للدخول، وملفاً لحفظ اللغة التي اخترتها.',
            'يحفظ متصفحك اختيارك للوضع الليلي/النهاري وحالة القائمة وظهور النافذة المنبثقة. لا نستخدم ملفات تتبع إعلانية.',
        ]],
        ['keep', 'مدة الاحتفاظ', [
            'نحتفظ ببيانات حسابك طالما الحساب مفتوح.',
            'نحتفظ بسجل الطلبات والرصيد لأغراض محاسبية ولحل أي خلاف، حتى بعد إغلاق الحساب، للمدة التي يتطلبها القانون.',
        ]],
        ['security', 'حماية البيانات', [
            'كلمات المرور مخزنة بشكل <b>مشفّر</b> ولا يستطيع أحد قراءتها، بما في ذلك الإدارة.',
            'يقتصر الوصول إلى البيانات على الإدارة، وكل عملية على الرصيد مسجّلة ولا يمكن تعديلها.',
            'ننصحك باستخدام كلمة مرور قوية وعدم مشاركتها، ومراجعة «آخر تسجيل دخول» بانتظام.',
        ]],
        ['rights', 'حقوقك', [
            'يمكنك عرض بياناتك وتعديلها من <b>ملفي الشخصي</b>، وتنزيل طلباتك وكشف حسابك كملف Excel.',
            'يمكنك طلب نسخة من بياناتك أو حذف حسابك بالتواصل معنا؛ مع الاحتفاظ بالسجلات المالية المطلوبة قانونياً.',
        ]],
        ['children', 'الأطفال', [
            'الموقع غير موجّه لمن هم دون 18 عاماً، ولا نجمع بياناتهم عن قصد.',
        ]],
        ['changes', 'تعديل السياسة', [
            'قد نحدّث هذه السياسة من وقت لآخر، ويظهر تاريخ آخر تحديث أعلى الصفحة.',
        ]],
    ] : [
        ['collect', 'What we collect', [
            '<b>Account details</b>: your name, username, email, phone number and the address you enter when you register.',
            '<b>Order details</b>: what you enter so a service can be carried out, such as an IMEI, serial number or email, and the order\'s result.',
            '<b>Credit history</b>: every top-up, charge and refund on your account.',
            '<b>Technical data</b>: your IP address and sign-in times, to protect your account and show you your "Last Login".',
        ]],
        ['use', 'How we use your data', [
            'To create your account, carry out your orders and show you their results.',
            'To manage your balance and statement and to answer your questions.',
            'To protect the site and detect misuse and unauthorised sign-in attempts.',
        ]],
        ['share', 'Sharing', [
            'When an order is carried out by an outside provider, we send them <b>only</b> what that order needs (for example the IMEI), never your account details.',
            'We do not sell, rent or share your data for marketing.',
            'We may disclose data when an authority requires it by law.',
        ]],
        ['cookies', 'Cookies and storage', [
            'We use a necessary session cookie to keep you signed in, and one to remember the language you chose.',
            'Your browser stores your dark / light choice, the menu state and whether you have seen the popup. We use no advertising or tracking cookies.',
        ]],
        ['keep', 'How long we keep data', [
            'We keep your account details while your account is open.',
            'Order and credit records are kept for accounting and to settle any dispute, also after an account is closed, for as long as the law requires.',
        ]],
        ['security', 'Security', [
            'Passwords are stored <b>hashed</b>: nobody can read them, including the administrator.',
            'Access to data is limited to the administrator, and every balance change is recorded and cannot be edited.',
            'Use a strong password, never share it, and check your "Last Login" regularly.',
        ]],
        ['rights', 'Your rights', [
            'You can view and edit your details in <b>My Profile</b>, and download your orders and statement as Excel files.',
            'You can ask us for a copy of your data or to delete your account by contacting us; financial records the law requires are kept.',
        ]],
        ['children', 'Children', [
            'This site is not meant for anyone under 18, and we do not knowingly collect their data.',
        ]],
        ['changes', 'Changes to this policy', [
            'We may update this policy from time to time; the date of the last update is shown at the top.',
        ]],
    ];
@endphp

@section('title', $ar ? 'سياسة الخصوصية' : 'Privacy Policy')
@section('page-title', $ar ? 'سياسة الخصوصية' : 'Privacy Policy')
@section('eyebrow', $ar ? 'قانوني' : 'Legal')

@section('content')
    @include('partials.legal-body')
@endsection
