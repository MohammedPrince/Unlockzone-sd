@extends('layouts.panel')

{{--
    Admin manual. One page, two languages: the text follows the language picked in the top bar (EN / عربي).
    Each section: an id (for the contents list), a title and a list of steps. <b> is allowed in steps.
    Keep it in step with the screens: when a feature changes, update both languages here.
--}}
@php
    $ar = app()->getLocale() === 'ar';
    $site = setting('site_name');
    $apiUrl = url('/');

    $sections = $ar ? [
        ['start', 'البداية', [
            'ادخل إلى لوحة التحكم من <b>/admin/login</b> باسم المستخدم وكلمة المرور الخاصة بالمسؤول.',
            'بعد أول دخول غيّر كلمة المرور من <b>الملف الشخصي</b> (اضغط على اسمك أعلى الصفحة).',
            'القائمة الجانبية تحتوي كل الأقسام. زر <b>☰</b> يخفيها أو يظهرها، ومن الهاتف يفتحها.',
            'غيّر اللغة من <b>EN / عربي</b> والوضع الليلي أو النهاري من زر <b>الشمس / القمر</b>.',
            'جرس <b>الإشعارات</b> يعرض العملاء الجدد والطلبات الجديدة وما حدث مؤخراً.',
            'زر <b>شحن رصيد</b> البرتقالي في الأعلى يفتح شاشة شحن رصيد العميل مباشرة من أي صفحة.',
            'كل صفحة فيها مربع <b>كيف تعمل هذه الصفحة</b>؛ اضغط <b>إخفاء</b> إذا لم تعد تحتاجه.',
        ]],
        ['dashboard', 'لوحة التحكم', [
            'تعرض أرقام اليوم: الإيرادات، الشحن، الطلبات حسب الحالة، العملاء الجدد ومصادر الزوار.',
            'تظهر آخر الطلبات؛ اضغط على أي طلب لمعالجته.',
            'العدد بجانب <b>طلبات الخدمات</b> في القائمة هو الطلبات التي ما زالت مفتوحة.',
        ]],
        ['categories', 'الأقسام وحقول الإدخال', [
            'القسم يجمع خدمات متشابهة، مثل <b>خدمات EMI</b>. افتح <b>الأقسام</b> واضغط <b>قسم جديد</b>.',
            'أضف للقسم <b>حقول الإدخال</b> التي قد تطلبها خدماته: IMEI، الرقم التسلسلي SN، البريد الإلكتروني، كود القفل، نص أو رقم.',
            'كل نوع يُتحقق منه تلقائياً: رقم IMEI يجب أن يكون 15 رقماً، والبريد يجب أن يكون صحيحاً.',
            'حقل رقمي اسمه <b>QTY</b> أو <b>Quantity</b> أو <b>الكمية</b> يجعل السعر = سعر الوحدة × الكمية.',
            'زر <b>إيقاف / تفعيل</b> يخفي القسم وكل خدماته عن العملاء دون حذفها.',
            '<b>حذف القسم</b> يحذف خدماته أيضاً، ويُرفض إذا كان فيه طلب قيد الانتظار أو التنفيذ. الخدمات التي لها طلبات منتهية تُؤرشف ويبقى العملاء يرون طلباتهم باللون الرمادي.',
        ]],
        ['services', 'الخدمات', [
            'من <b>الخدمات</b> اضغط <b>خدمة جديدة</b>: اختر القسم، الاسم، السعر، مدة التنفيذ، والوصف.',
            'ضع علامة على <b>حقول الإدخال المطلوبة</b> التي يجب على العميل تعبئتها لهذه الخدمة.',
            '<b>خدمة فورية</b> تضيف علامة <b>Instant</b> الصفراء بجانب السعر.',
            '<b>نشطة</b> = معروضة للبيع. <b>غير نشطة</b> = مخفية عن العملاء مع بقاء سجلها.',
            'تغيير السعر يؤثر على الطلبات الجديدة فقط؛ الطلبات السابقة تبقى بالمبلغ الذي دُفع.',
            'لربط الخدمة بمزوّد API اختر <b>المزوّد</b> واكتب <b>رقم الخدمة عند المزوّد</b>؛ عندها تُرسل الطلبات تلقائياً.',
            'لنقل عدة خدمات إلى قسم آخر حددها في القائمة واستخدم <b>نقل</b>.',
            'علامة <b>جديد</b> تظهر على الخدمة لعدد الأيام المحدد في <b>الإعدادات</b>.',
        ]],
        ['import', 'استيراد الخدمات من ملف نصي', [
            'من <b>الإعدادات</b> ← <b>استيراد الخدمات من ملف نصي</b> ارفع قائمة أسعار المورد بصيغة <b>.txt</b>.',
            'السطر الأول: اسم القسم الذي تدخل فيه كل الخدمات. ثم ثلاثة أسطر لكل خدمة: الاسم، سطر المورد (يُتجاهل)، السعر ومدة التنفيذ.',
            'ستظهر لك كل الخدمات قبل الاستيراد؛ راجعها ثم اضغط تأكيد، أو ألغِ.',
        ]],
        ['customers', 'العملاء', [
            'يسجّل العملاء بأنفسهم، أو تنشئ الحساب من <b>العملاء</b> ← <b>عميل جديد</b>. تُنشأ له محفظة فارغة تلقائياً.',
            '<b>نشط</b>: يستطيع الدخول والطلب. <b>بانتظار الموافقة</b>: سجّل وينتظر موافقتك (اضغط <b>الموافقة على الحساب</b>). <b>موقوف</b>: لا يستطيع الدخول، ورصيده وسجله محفوظان.',
            'افتح ملف العميل لترى رصيده وإجمالي الشحن والصرف وآخر طلباته وكشف حسابه وآخر دخول له.',
            '<b>إيقاف من كل الخدمات</b>: يستطيع الدخول ورؤية سجله لكنه لا يستطيع طلب أي خدمة.',
            '<b>إيقاف من خدمة واحدة</b>: اختر الخدمة من <b>صلاحية الخدمات</b>؛ و<b>السماح</b> يعيدها.',
            '<b>تصدير</b> ينزّل قائمة العملاء كملف Excel.',
        ]],
        ['credits', 'الرصيد وكشف الحساب', [
            'العملاء يدفعون مسبقاً. عندما يدفع لك العميل، اضغط <b>شحن رصيد</b>، اختر العميل، اكتب المبلغ، وسجّل مرجع الدفع في الملاحظة.',
            '<b>تعديل</b>: تصحيح يدوي بالزيادة أو النقصان، والسبب إلزامي.',
            '<b>خصم</b>: يتم تلقائياً عند طلب خدمة. <b>استرداد</b>: يتم تلقائياً عند فشل الطلب أو إلغائه.',
            'كل تغيير في الرصيد له قيد في <b>الرصيد وكشف الحساب</b> بالرصيد قبل وبعد. لا يمكن تعديل القيود أو حذفها، ولا يمكن أن ينزل الرصيد تحت الصفر.',
            'صفِّ الكشف حسب العميل أو النوع أو التاريخ، واضغط <b>تصدير</b> لتنزيله.',
        ]],
        ['requests', 'معالجة الطلبات', [
            'كل طلب يظهر في <b>طلبات الخدمات</b>، والمبلغ يُخصم من العميل لحظة الطلب.',
            'افتح الطلب لترى ما كتبه العميل. في <b>معالجة هذا الطلب</b> اختر الحالة الجديدة.',
            '<b>مكتمل</b>: اكتب النتيجة (كود الفتح، التأكيد، التعليمات...) في <b>النتيجة للعميل</b>؛ يراها العميل كما كتبتها ويصله بريد.',
            '<b>فشل / ملغي</b>: اكتب السبب؛ يُغلق الطلب ويُعاد المبلغ كاملاً للعميل تلقائياً مرة واحدة فقط.',
            'الطلب المكتمل أو الفاشل أو الملغي نهائي ولا يمكن تغييره.',
            'طلبات الخدمات المربوطة بمزوّد تُرسل وتُتابع تلقائياً. إذا رفضها المزوّد يظهر السبب، ويمكنك الضغط <b>إرسال إلى المزوّد</b> مرة أخرى أو إغلاقها بنفسك.',
            'القائمة تتحدث تلقائياً. استخدم البحث والفلاتر، و<b>تصدير</b> للتنزيل.',
        ]],
        ['providers', 'مزوّدو الـ API (الشراء من سيرفرات أخرى)', [
            'من <b>مزوّدو الـ API</b> اضغط <b>ربط مزوّد</b>: اختر نوع الـ API، واكتب عنوان السيرفر، واسم المستخدم ومفتاح الـ API من حسابك عند المزوّد.',
            '<b>اختبار</b> يتصل بالمزوّد ويعرض رصيدك عنده. أي خطأ يظهر تحت اسمه.',
            '<b>مزامنة الخدمات</b> ينزّل قائمة أسعار المزوّد.',
            'في قائمة الأسعار حدد الخدمات، واختر القسم ونسبة الربح، ثم <b>استيراد الخدمات المحددة</b>. سعرك = سعر المزوّد + نسبة الربح.',
            'عندما يطلب العميل خدمة مربوطة يُخصم منه هنا ويُرسل الطلب للمزوّد؛ النجاح يكمل الطلب بالنتيجة، والرفض يعيد المبلغ للعميل.',
            'النتائج تُجلب كل دقيقة عند تشغيل المجدول، وعند فتح صفحة الطلب، أو بزر <b>فحص الطلبات المفتوحة الآن</b>.',
            '<b>حذف</b> المزوّد لا يحذف خدماته، بل تصبح معالجتها يدوية.',
        ]],
        ['api-clients', 'عملاء الـ API (بيع خدماتك لسيرفرات أخرى)', [
            'هذا هو القسم الثاني في صفحة <b>مزوّدو الـ API</b>: أنت المزوّد، وسيرفر آخر يطلب خدماتك تلقائياً.',
            'أنشئ أولاً <b>حساب عميل</b> للسيرفر الآخر واشحن له رصيداً؛ كل طلباته تُدفع من هذا الرصيد وتظهر في طلبات الخدمات.',
            'اضغط <b>إضافة عميل API</b> واختر العميل. يظهر <b>مفتاح الـ API مرة واحدة فقط</b> بعد الإنشاء، فانسخه وأرسله لهم بطريقة آمنة.',
            'أعطهم: <b>عنوان السيرفر</b> <code dir="ltr">' . e($apiUrl) . '</code>، <b>اسم المستخدم</b> = اسم مستخدم العميل، و<b>مفتاح الـ API</b>. يضيفونك عندهم كمزوّد من نوع DHRU Fusion.',
            '<b>الخدمات</b>: <b>كل الخدمات</b> (وما تضيفه لاحقاً)، أو <b>الخدمات التي أحددها فقط</b>.',
            '<b>السعر لهذا العميل (%)</b>: 0 = سعر موقعك، 10 = أعلى بـ 10%، ‎-5 = خصم 5%. ويمكنك كتابة <b>سعر العميل</b> ثابت بجانب أي خدمة ليحل محل النسبة.',
            '<b>إيقاف</b> يرفض كل طلباته فوراً حتى تضغط <b>إعادة التفعيل</b>. <b>مفتاح جديد</b> يلغي المفتاح القديم فوراً. <b>حذف</b> يلغي الوصول فقط، ويبقى حساب العميل ورصيده وطلباته.',
            '<b>عناوين IP المسموحة</b> (اختياري): إذا كتبتها لا يُقبل المفتاح إلا من هذه العناوين.',
            'إيقاف العميل من خدمة أو من كل الخدمات في ملفه ينطبق على الـ API أيضاً.',
        ]],
        ['site', 'شريط الإعلانات والإعدادات', [
            '<b>شريط الإعلانات</b>: سطر متحرك أعلى صفحات العملاء. أضف رسالة، أو أخفها مؤقتاً، أو أوقف الشريط كله، ويمكن عرض الخدمات الجديدة فيه تلقائياً.',
            '<b>الإعدادات ← عام</b>: اسم المنصة، الشعار (يُستخدم أيضاً كأيقونة المتصفح)، وسيلة التواصل، والتنبيه الثابت.',
            '<b>أزرار واتساب وتيليجرام</b>: فعّل كل زر واكتب الرقم أو اسم المستخدم.',
            '<b>الصفحة الرئيسية</b>: نص الترحيب، النافذة المنبثقة، بيانات التواصل، والعرض المؤقت مع وقت انتهائه.',
            '<b>الخدمات والطلبات</b>: مدة علامة "جديد" ونص شروط الخدمة وهل يجب على العميل الموافقة عليها.',
            '<b>التسجيل</b>: هل يمكن للعملاء التسجيل، وهل تحتاج الحسابات الجديدة موافقتك.',
        ]],
        ['reports', 'التقارير وسجل النشاط', [
            '<b>التقارير</b>: اختر الفترة. <b>صافي الإيرادات</b> = ما صرفه العملاء على الخدمات ناقص الاستردادات. <b>الشحن</b> = رصيد أضفته (مال مستلم وليس ربحاً بعد). <b>نسبة النجاح</b> = المكتملة من كل الطلبات المغلقة.',
            'تعرض أيضاً أكثر الخدمات والعملاء طلباً وتوزيع الأقسام، ويمكن تصديرها.',
            '<b>سجل النشاط</b>: كل عملية مهمة مسجلة بمن قام بها ومتى (شحن، تعديل، تغيير حالة، إعدادات، عملاء API...).',
        ]],
        ['tools', 'أدوات السيرفر والبريد', [
            'بعد رفع نسخة جديدة للاستضافة شغّل بالترتيب: <b>نسخة احتياطية لقاعدة البيانات</b>، ثم <b>Composer install</b> (فقط إذا تغيّر composer)، ثم <b>مسح الكاش</b>، ثم <b>تشغيل التحديثات (Migrations)</b>، ثم <b>Optimize</b>.',
            'النسخ الاحتياطية تظهر في نفس الصفحة للتنزيل أو الحذف. خذ نسخة قبل أي تغيير كبير.',
            '<b>إعدادات البريد</b>: اكتب بريد المسؤول وحساب البريد من الاستضافة (SMTP)، احفظ، ثم أرسل بريد اختبار.',
            'يصلك بريد لكل عميل جديد وكل طلب جديد، ويصل العميل بريد عند اكتمال طلبه أو فشله أو إلغائه.',
            'إذا تعطلت الأدوات فهي متوقفة من ملف <b>.env</b> (SERVER_TOOLS_ENABLED).',
        ]],
        ['safety', 'نصائح وأمان', [
            'استخدم كلمة مرور قوية للمسؤول ولا تشاركها.',
            'لا ترسل مفاتيح الـ API في رسائل عامة. إذا تسرب مفتاح عميل اضغط <b>مفتاح جديد</b> فوراً.',
            'سجّل مرجع الدفع في كل عملية شحن؛ هذا يحل أي خلاف لاحقاً.',
            'راجع رصيدك عند المزوّدين بانتظام بزر <b>اختبار</b>، فالطلبات ترفض إذا نفد رصيدك عندهم.',
            'لا تصحح رصيداً بحذف شيء: استخدم <b>تعديل</b> مع سبب واضح.',
        ]],
    ] : [
        ['start', 'Getting started', [
            'Sign in at <b>/admin/login</b> with the administrator username and password.',
            'After your first sign-in, change the password under <b>Profile</b> (tap your name at the top).',
            'The side menu holds every section. The <b>☰</b> button hides or shows it, and opens it on a phone.',
            'Switch the language with <b>EN / عربي</b> and dark or light mode with the <b>sun / moon</b> button.',
            'The <b>bell</b> lists new customers, new orders and other recent events.',
            'The orange <b>Recharge</b> button at the top opens the customer recharge screen from any page.',
            'Every page has a <b>How it works</b> box; tap <b>Hide</b> once you no longer need it.',
        ]],
        ['dashboard', 'Dashboard', [
            'Shows today\'s figures: revenue, recharges, requests by status, new customers and where visitors came from.',
            'The latest requests are listed; tap one to process it.',
            'The number next to <b>Service requests</b> in the menu is how many requests are still open.',
        ]],
        ['categories', 'Categories and input fields', [
            'A category groups related services, for example <b>EMI Services</b>. Open <b>Categories</b> and tap <b>New category</b>.',
            'Add the <b>input fields</b> its services may ask for: IMEI, Serial Number (SN), Email, Lock Code, text or number.',
            'Each type is checked for you: an IMEI must be 15 digits, an email must be valid.',
            'A number field named <b>QTY</b>, <b>Quantity</b> or <b>الكمية</b> makes the price = unit price × quantity.',
            '<b>Deactivate / Activate</b> hides the category and all its services from customers without deleting anything.',
            '<b>Deleting a category</b> deletes its services too. It is refused while an order in it is pending or processing. Services with finished orders are archived; customers still see those orders, greyed out.',
        ]],
        ['services', 'Services', [
            'In <b>Services</b> tap <b>New service</b>: pick the category, then enter the name, price, delivery time and description.',
            'Tick the <b>Required input fields</b> the customer must fill in for this service.',
            '<b>Instant service</b> adds a yellow <b>Instant</b> tag next to the price.',
            '<b>Active</b> = on sale. <b>Inactive</b> = hidden from customers, history kept.',
            'Changing a price only affects new orders; existing requests keep the amount they were charged.',
            'To link a service to an API provider, choose the <b>Provider</b> and enter the <b>Service ID at the provider</b>; orders are then sent automatically.',
            'To move several services to another category, tick them in the list and use <b>Move</b>.',
            'The <b>New</b> tag shows on a service for the number of days set in <b>Settings</b>.',
        ]],
        ['import', 'Importing services from a text file', [
            'In <b>Settings</b> → <b>Import services from a text file</b>, upload a supplier price list saved as <b>.txt</b>.',
            'First line: the category every service goes into. Then three lines per service: name, supplier line (ignored), price and delivery time.',
            'Every service found is shown before anything is imported; check them, then confirm or cancel.',
        ]],
        ['customers', 'Customers', [
            'Customers register themselves, or you create the account under <b>Customers</b> → <b>New customer</b>. An empty wallet is created with it.',
            '<b>Active</b>: can sign in and order. <b>Pending</b>: registered and waiting for you (tap <b>Approve account</b>). <b>Suspended</b>: cannot sign in; balance and history are kept.',
            'Open a customer to see their balance, total recharged and spent, recent requests, ledger and last sign-in.',
            '<b>Suspend from all services</b>: they can still sign in and see their history, but cannot order anything.',
            '<b>Suspend from one service</b>: choose the service under <b>Service access</b>; <b>Allow</b> gives it back.',
            '<b>Export</b> downloads the customer list as an Excel file.',
        ]],
        ['credits', 'Credits and the ledger', [
            'Customers prepay. When a customer pays you, tap <b>Recharge</b>, pick the customer, enter the amount and put the payment reference in the note.',
            '<b>Adjustment</b>: a manual correction up or down; a reason is required.',
            '<b>Deduction</b>: taken automatically when a service is ordered. <b>Refund</b>: returned automatically when a request fails or is cancelled.',
            'Every balance change has an entry in <b>Credits &amp; ledger</b> with the balance before and after. Entries can never be edited or deleted, and a balance can never go below zero.',
            'Filter by customer, type or date, and tap <b>Export</b> to download.',
        ]],
        ['requests', 'Processing requests', [
            'Every order appears in <b>Service requests</b>; the price was taken from the customer when they ordered.',
            'Open a request to see what the customer entered. Under <b>Process this request</b> choose the new status.',
            '<b>Completed</b>: type the result (unlock code, confirmation, instructions...) in <b>Result for the customer</b>. They see it exactly as typed and get an email.',
            '<b>Failed / Cancelled</b>: give the reason. The request closes and the full amount goes back to the customer automatically, exactly once.',
            'Completed, failed and cancelled are final and cannot be changed.',
            'Requests for provider-linked services are sent and followed automatically. If the provider refuses one, the reason is shown; tap <b>Send to provider</b> again or close it yourself.',
            'The list refreshes by itself. Use search and filters, and <b>Export</b> to download.',
        ]],
        ['providers', 'API providers (buying from other servers)', [
            'In <b>API providers</b> tap <b>Connect a provider</b>: choose the API type and enter the server address, plus the username and API key from your account at the provider.',
            '<b>Test</b> signs in and shows your balance there. Any error is shown under the provider\'s name.',
            '<b>Sync services</b> downloads the provider\'s price list.',
            'In the price list, tick services, choose a category and your profit margin, then <b>Import selected services</b>. Your price = provider price + margin.',
            'When a customer orders a linked service, it is charged here and forwarded. Success completes the request with the result; a rejection refunds the customer.',
            'Results are fetched every minute when the scheduler runs, whenever a request page is opened, or with <b>Check open orders now</b>.',
            '<b>Deleting</b> a provider keeps its services; they become manual.',
        ]],
        ['api-clients', 'API clients (selling your services to other servers)', [
            'This is the second section on the <b>API providers</b> page: here you are the provider, and another server orders your services automatically.',
            'First create a <b>customer account</b> for the other server and give it credit. All its orders are paid from that credit and appear in Service requests.',
            'Tap <b>Add API client</b> and pick the customer. The <b>API key is shown only once</b> after creating it: copy it and send it to them securely.',
            'Give them: <b>Server address</b> <code dir="ltr">' . e($apiUrl) . '</code>, <b>Username</b> = the customer\'s username, and the <b>API key</b>. They add you as a DHRU Fusion provider.',
            '<b>Which services</b>: <b>All services</b> (including ones you add later) or <b>Only the services I tick</b>.',
            '<b>Price for this client (%)</b>: 0 = your site price, 10 = 10% more, -5 = 5% discount. A fixed <b>Client price</b> typed next to a service replaces the percentage for that service.',
            '<b>Suspend</b> refuses all their calls at once until you tap <b>Resume</b>. <b>New key</b> stops the old key immediately. <b>Delete</b> removes API access only; the customer account, credit and orders stay.',
            '<b>Allowed IP addresses</b> (optional): when filled in, the key only works from those addresses.',
            'Suspending the customer from a service, or from all services, on their profile applies to the API too.',
        ]],
        ['site', 'Announcement bar and Settings', [
            '<b>Announcement bar</b>: a scrolling line at the top of customer pages. Add a message, hide it for later, or turn the whole bar off. New services can be listed in it automatically.',
            '<b>Settings → General</b>: platform name, logo (also used as the browser tab icon), support contact and the fixed notice.',
            '<b>WhatsApp and Telegram chat buttons</b>: enable each one and enter the number or username.',
            '<b>Home page</b>: welcome text, the popup, contact details and the flash offer with its end time.',
            '<b>Services and orders</b>: how long the "New" tag lasts, the terms of service text and whether customers must accept it.',
            '<b>Registration</b>: whether customers can register and whether new accounts need your approval.',
        ]],
        ['reports', 'Reports and Activity log', [
            '<b>Reports</b>: choose a period. <b>Net revenue</b> = what customers spent on services minus refunds. <b>Recharges</b> = credit you added (money received, not yet earned). <b>Success rate</b> = completed out of all closed requests.',
            'It also shows top services, top customers and the category breakdown, and can be exported.',
            '<b>Activity log</b>: every important action with who did it and when (recharges, adjustments, status changes, settings, API clients...).',
        ]],
        ['tools', 'Server Tools and email', [
            'After uploading a new version to the hosting, run in this order: <b>Database backup</b>, <b>Composer install</b> (only if composer files changed), <b>Clear caches</b>, <b>Run migrations</b>, then <b>Optimize</b>.',
            'Backups are listed on the same page to download or delete. Take one before any big change.',
            '<b>Email settings</b>: enter the admin email and the hosting email account (SMTP), save, then send a test email.',
            'You get an email for every new customer and every new order; customers get one when their order is completed, failed or cancelled.',
            'If the tool buttons are off, they are disabled in <b>.env</b> (SERVER_TOOLS_ENABLED).',
        ]],
        ['safety', 'Tips and safety', [
            'Use a strong administrator password and never share it.',
            'Never post API keys in public chats. If a client\'s key leaks, tap <b>New key</b> at once.',
            'Write the payment reference on every recharge; it settles any dispute later.',
            'Check your balance at each provider regularly with <b>Test</b>: orders are refused when your credit there runs out.',
            'Never fix a balance by deleting something: use an <b>Adjustment</b> with a clear reason.',
        ]],
    ];
@endphp

@section('title', $ar ? 'دليل المسؤول' : 'Admin manual')
@section('eyebrow', $ar ? 'المساعدة' : 'Help')
@section('page-title', $ar ? 'دليل المسؤول' : 'Admin manual')
@section('page-description', $ar ? 'كيف تدير ' . $site . ' من الإعداد إلى معالجة الطلبات وبيع خدماتك عبر الـ API.' : 'How to run ' . $site . ', from setting up services to processing orders and selling through your API.')

@section('page-actions')
    <a class="btn" href="{{ route('locale', $ar ? 'en' : 'ar') }}">{{ $ar ? 'English' : 'عربي' }}</a>
    <button type="button" class="btn" onclick="window.print()">{{ $ar ? 'طباعة / PDF' : 'Print / PDF' }}</button>
@endsection

@section('content')
    <div class="guide">
        <div class="card guide-intro">
            <div class="card-body">
                <h2>{{ $ar ? 'المحتويات' : 'Contents' }}</h2>
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
    </div>
@endsection
