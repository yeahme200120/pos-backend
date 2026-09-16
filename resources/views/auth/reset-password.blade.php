<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F5F5F5;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 32px;
            max-width: 420px;
            width: 90%;
        }
        h1 {
            font-size: 22px;
            color: #222;
            margin: 0 0 8px 0;
        }
        p.subtitle {
            color: #666;
            font-size: 13px;
            margin: 0 0 24px 0;
        }
        label {
            display: block;
            font-size: 13px;
            color: #444;
            margin-bottom: 6px;
            font-weight: 500;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            margin-bottom: 16px;
            background: #E7DDE8;
            border: none;
            border-radius: 6px;
        }
        input:focus {
            outline: 2px solid #9AC53B;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #303030;
            color: #FFF;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            letter-spacing: 0.5px;
            cursor: pointer;
            font-weight: 500;
        }
        button:hover {
            background: #444;
        }
        .error {
            background: #FFEBEE;
            color: #C62828;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
            border: 1px solid #FFCDD2;
        }
        .hint {
            font-size: 11px;
            color: #888;
            margin-top: -10px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Restablecer contraseña</h1>
        <p class="subtitle">
            Define una nueva contraseña para tu cuenta.
        </p>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $email) }}"
                required
                autofocus
            >

            <label for="password">Nueva contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                minlength="8"
            >
            <div class="hint">
                Mínimo 8 caracteres. Se recomienda mayúscula, minúscula y número.
            </div>

            <label for="password_confirmation">Confirmar contraseña</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
                minlength="8"
            >

            <button type="submit">Restablecer contraseña</button>
        </form>
    </div>
</body>
</html>