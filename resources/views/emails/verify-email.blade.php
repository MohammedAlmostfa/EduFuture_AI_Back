<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>تأكيد البريد الإلكتروني - EduFuture AI</title>
    <style>
        body {
            font-family: 'Segoe UI', 'Tajawal', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            margin: 0;
            padding: 20px;
            color: #1e293b;
        }

        .container {
            max-width: 560px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 0;
            border-radius: 32px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            padding: 32px 28px;
            text-align: center;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            padding: 8px 24px;
            border-radius: 60px;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 12px -6px rgba(0, 0, 0, 0.2);
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
            stroke: #4f46e5;
            stroke-width: 1.8;
        }

        .logo-text {
            color: white;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 16px 0 0;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .content {
            padding: 32px 32px 24px;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
        }

        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .app-name {
            font-weight: 700;
            color: #4f46e5;
            background: #eef2ff;
            padding: 2px 10px;
            border-radius: 30px;
            display: inline-block;
        }

        .code-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 20px;
            text-align: center;
            margin: 28px 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        }

        .code {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 6px;
            color: #1e293b;
            background: white;
            display: inline-block;
            padding: 12px 28px;
            border-radius: 60px;
            font-family: monospace;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .validity {
            font-size: 13px;
            color: #64748b;
            margin-top: 12px;
        }

        .footer {
            background-color: #f8fafc;
            padding: 20px 32px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #64748b;
        }

        .footer a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        hr {
            margin: 16px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 600px) {
            .container {
                border-radius: 24px;
            }
            .header {
                padding: 24px 20px;
            }
            .content {
                padding: 24px 24px 20px;
            }
            .code {
                font-size: 28px;
                letter-spacing: 4px;
                padding: 8px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- الرأس المتدرج باللون indigo -->
        <div class="header">
            <div class="logo">
                <div class="logo-icon">
                    <!-- أيقونة كتاب مفتوح (رمز التعلم) -->
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 6.253v13" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                </div>
                <span class="logo-text">EduFuture AI</span>
            </div>
            <h1>تأكيد البريد الإلكتروني</h1>
        </div>

        <!-- المحتوى الرئيسي -->
        <div class="content">
            <p class="greeting">مرحباً <strong>{{ $user['email'] }}</strong>،</p>
            <p>شكراً لتسجيلك في <span class="app-name">EduFuture AI</span> – منصة التعليم الذكي.</p>
            <p>لإتمام عملية التسجيل وتفعيل حسابك، يرجى استخدام رمز التأكيد أدناه:</p>

            <div class="code-box">
                <div class="code">{{ $code }}</div>
                <div class="validity">⏱️ هذا الرمز صالح لفترة محدودة من الوقت.</div>
            </div>

            <p>إذا لم تطلب هذا التأكيد، يمكنك تجاهل هذا البريد الإلكتروني. لن يتم تفعيل حسابك بدون هذا الرمز.</p>
            <p>فريق <strong>EduFuture AI</strong> يتمنى لك رحلة تعلم موفقة 🎓</p>
        </div>

        <!-- التذييل -->
        <div class="footer">
            <p>هل تحتاج مساعدة؟ تواصل معنا على <a href="mailto:support@edufuture.ai">support@edufuture.ai</a></p>
            <hr />
            <p>&copy; 2026 EduFuture AI. جميع الحقوق محفوظة.</p>
            <p style="font-size: 11px;">هذا البريد الإلكتروني تم إرساله تلقائياً، يرجى عدم الرد عليه.</p>
        </div>
    </div>
</body>

</html>
