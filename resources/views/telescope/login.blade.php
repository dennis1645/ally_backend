<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ALLY Telescope Portal Admin</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0F172A;
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.25) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.2) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(16, 185, 129, 0.15) 0px, transparent 50%);
            padding: 20px;
            overflow-x: hidden;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #4F46E5 0%, #3B82F6 100%);
            border-radius: 18px;
            font-size: 32px;
            margin-bottom: 16px;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);
        }

        .brand-header h1 {
            color: #FFFFFF;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .brand-header p {
            color: #94A3B8;
            font-size: 14px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #E2E8F0;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1.5px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 14px 44px 14px 16px;
            color: #FFFFFF;
            font-size: 14.5px;
            font-weight: 500;
            outline: none;
            transition: all 0.25s ease;
        }

        .input-wrapper input:focus {
            border-color: #6366F1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
            background: rgba(15, 23, 42, 0.85);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: #F8FAFC;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #4F46E5 0%, #3B82F6 100%);
            border: none;
            border-radius: 12px;
            padding: 15px;
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(79, 70, 229, 0.45);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .footer-text {
            text-align: center;
            color: #64748B;
            font-size: 12px;
            margin-top: 28px;
        }

        .footer-text span {
            color: #818CF8;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-header">
            <div class="brand-badge">🔭</div>
            <h1>ALLY Telescope Admin</h1>
            <p>Sistem Pemantauan Telemetri & Log API</p>
        </div>

        <form id="telescopeLoginForm">
            <div class="form-group">
                <label for="email">Email Admin Juna</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" value="juna.admin@gmail.com" placeholder="juna.admin@gmail.com" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Masukkan password admin" required>
                    <button type="button" class="toggle-password" id="togglePassword" title="Tampilkan Password">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="btnSubmit">
                Masuk ke Telescope 🚀
            </button>
        </form>

        <div class="footer-text">
            &copy; {{ date('Y') }} <span>ALLY Mentorship Platform</span>. Access restricted to authorized admins.
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const form = document.getElementById('telescopeLoginForm');
        const submitBtn = document.getElementById('btnSubmit');

        toggleBtn.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleBtn.textContent = type === 'password' ? '👁️' : '🙈';
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = passwordInput.value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Memproses Autentikasi... ⏳';

            Swal.fire({
                title: 'Memverifikasi Akses Admin...',
                text: 'Harap tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch('{{ url("/telescope-login") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (response.ok && data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Autentikasi Berhasil! 🎉',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.redirect_url;
                    });
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Masuk ke Telescope 🚀';

                    Swal.fire({
                        icon: 'error',
                        title: 'Akses Ditolak!',
                        text: data.message || 'Email atau password tidak sesuai.',
                        confirmButtonColor: '#4F46E5'
                    });
                }
            } catch (err) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Masuk ke Telescope 🚀';

                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Sistem',
                    text: 'Terjadi masalah jaringan atau server: ' + err.message,
                    confirmButtonColor: '#4F46E5'
                });
            }
        });
    </script>
</body>
</html>
