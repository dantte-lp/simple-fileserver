<?php
/**
 * Утилита для генерации хешей паролей с поддержкой RBAC
 * Запустите из командной строки: php generate-password.php
 */

echo "=== Генератор хешей паролей для File Manager с RBAC ===\n\n";

// Проверка запуска из командной строки
if (php_sapi_name() !== 'cli') {
    die("Этот скрипт должен запускаться только из командной строки!\n");
}

// Функция для безопасного ввода пароля
function getPassword($prompt = "Введите пароль: ") {
    echo $prompt;

    // Отключаем эхо для безопасного ввода
    if (PHP_OS_FAMILY === 'Windows') {
        $password = trim(shell_exec('powershell -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"'));
    } else {
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    }

    return $password;
}

// Основной цикл
while (true) {
    echo "Выберите действие:\n";
    echo "1. Сгенерировать хеш для нового пользователя\n";
    echo "2. Проверить существующий пароль\n";
    echo "3. Сгенерировать случайный безопасный пароль\n";
    echo "4. Показать пример конфигурации с RBAC\n";
    echo "0. Выход\n\n";
    echo "Ваш выбор: ";

    $choice = trim(fgets(STDIN));
    echo "\n";

    switch ($choice) {
        case '1':
            // Генерация хеша
            echo "Введите имя пользователя: ";
            $username = trim(fgets(STDIN));

            echo "Выберите роль:\n";
            echo "1. admin (полный доступ)\n";
            echo "2. user (без удаления)\n";
            echo "Ваш выбор (1 или 2): ";
            $roleChoice = trim(fgets(STDIN));

            $role = ($roleChoice === '1') ? 'admin' : 'user';

            $password = getPassword("Введите пароль: ");
            $confirmPassword = getPassword("Подтвердите пароль: ");

            if ($password !== $confirmPassword) {
                echo "❌ Пароли не совпадают!\n\n";
                break;
            }

            if (strlen($password) < 8) {
                echo "⚠️  Предупреждение: пароль короче 8 символов!\n";
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            echo "\n✅ Хеш сгенерирован успешно!\n";
            echo "Роль: " . ($role === 'admin' ? 'Администратор' : 'Пользователь') . "\n";
            echo "Добавьте эту запись в файл config/users.php:\n\n";
            echo "'$username' => [\n";
            echo "    'password' => '$hash',\n";
            echo "    'role' => '$role'\n";
            echo "],\n\n";

            // Сохранить в файл?
            echo "Сохранить в файл user_hash.txt? (y/n): ";
            if (trim(fgets(STDIN)) === 'y') {
                $content = "// Сгенерировано: " . date('Y-m-d H:i:s') . "\n";
                $content .= "// Роль: " . ($role === 'admin' ? 'Администратор' : 'Пользователь') . "\n";
                $content .= "'$username' => [\n";
                $content .= "    'password' => '$hash',\n";
                $content .= "    'role' => '$role'\n";
                $content .= "],\n";
                file_put_contents('user_hash.txt', $content);
                echo "✅ Сохранено в user_hash.txt\n\n";
            }
            break;

        case '2':
            // Проверка пароля
            echo "Введите хеш для проверки: ";
            $hash = trim(fgets(STDIN));

            $password = getPassword("Введите пароль для проверки: ");

            if (password_verify($password, $hash)) {
                echo "✅ Пароль верный!\n\n";
            } else {
                echo "❌ Пароль неверный!\n\n";
            }
            break;

        case '3':
            // Генерация случайного пароля
            echo "Длина пароля (по умолчанию 16): ";
            $length = (int)trim(fgets(STDIN)) ?: 16;

            $password = generateRandomPassword($length);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            echo "\n✅ Случайный пароль сгенерирован:\n";
            echo "Пароль: $password\n";
            echo "Хеш: $hash\n\n";
            break;

        case '4':
            // Показать пример
            echo "Пример файла config/users.php с RBAC:\n\n";
            echo <<<'CONFIG'
<?php
return [
    'users' => [
        // Администратор - полный доступ
        'admin' => [
            'password' => '$2y$10$...',  // Ваш хеш
            'role' => 'admin'
        ],

        // Обычный пользователь - без удаления
        'john' => [
            'password' => '$2y$10$...',  // Ваш хеш
            'role' => 'user'
        ],

        // Старый формат (будет работать как user)
        'legacy' => '$2y$10$...',  // Только хеш
    ],

    'permissions' => [
        'admin' => [
            'view' => true,
            'download' => true,
            'upload' => true,
            'delete' => true
        ],
        'user' => [
            'view' => true,
            'download' => true,
            'upload' => true,
            'delete' => false
        ]
    ]
];
CONFIG;
            echo "\n\n";
            break;
            
        case '0':
            echo "До свидания!\n";
            exit(0);
            
        default:
            echo "❌ Неверный выбор!\n\n";
    }
}

/**
 * Генерация случайного безопасного пароля
 */
function generateRandomPassword($length = 16) {
    $sets = [
        'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
        'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'numbers' => '0123456789',
        'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?'
    ];
    
    $all = '';
    $password = '';
    
    // Гарантируем хотя бы один символ из каждого набора
    foreach ($sets as $set) {
        $password .= $set[random_int(0, strlen($set) - 1)];
        $all .= $set;
    }
    
    // Заполняем остальную часть пароля
    for ($i = count($sets); $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    // Перемешиваем пароль
    return str_shuffle($password);
}