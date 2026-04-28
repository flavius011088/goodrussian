# Инструкция по установке Backend для GoodRussian

## 1. Подготовка MySQL БД

### Локально (для разработки)
```bash
# Подключиться к MySQL
mysql -u root -p

# Создать БД
CREATE DATABASE goodrussian CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Проверить создание
SHOW DATABASES;
```

### Или использовать phpMyAdmin
1. Открыть `http://localhost/phpmyadmin`
2. Создать новую БД: `goodrussian`
3. Выбрать charset: `utf8mb4`

---

## 2. Обновить конфиг ( `/config/db.php`)

Если используется нестандартная конфигурация:

```php
define('DB_HOST', 'localhost');      // Хост
define('DB_NAME', 'goodrussian');    // Имя БД
define('DB_USER', 'root');           // Пользователь
define('DB_PASS', '');               // Пароль (пусто для локального root)
```

---

## 3. Инициализировать схему БД

### Способ 1: Через SQL-скрипт
```bash
mysql -u root -p goodrussian < init.sql
```

### Способ 2: Вручную (через phpMyAdmin или CLI)
Выполнить SQL:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    language_level ENUM('A1','A2','B1','B2','C1','C2'),
    date_of_birth DATE,
    country VARCHAR(100),
    profession VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME,
    reset_token VARCHAR(255),
    reset_token_expires DATETIME,
    INDEX idx_email (email),
    INDEX idx_reset_token (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Способ 3: Через PHP скрипт
Создать временный файл `init_db.php`:

```php
<?php
require_once 'config/db.php';
init_database();
echo "Таблица users создана успешно!";
?>
```

Открыть в браузере: `http://localhost:8081/init_db.php`

---

## 4. Тестировать регистрацию

1. Открыть `http://localhost:8081/Account/Register.html`
2. Заполнить форму
3. Нажать "Создать личный кабинет"
4. Проверить в БД:
   ```sql
   SELECT id, email, first_name, created_at FROM users;
   ```

---

## 5. Тестировать вход

1. Открыть `http://localhost:8081/Account/Login.html`
2. Ввести email и пароль из регистрации
3. Нажать "Войти"
4. Должно перенаправить на `/Profile/EntranceTesting`

---

## 6. Проверить сессию

Добавить в нужное место для проверки авторизации:

```php
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // Пользователь не авторизован
    header('Location: /Account/Login.html');
    exit;
}

// Пользователь авторизован
echo "Привет, " . $_SESSION['user_name'];
?>
```

---

## 7. Конфигурация для Production

**ВАЖНО:** Перед публикацией на production:

1. **Не оставлять credentials в коде**
   - Использовать `.env` файл или переменные окружения
   - Добавить `.env` в `.gitignore`

2. **Пароль БД**
   - Создать отдельного пользователя MySQL с ограниченными привилегиями
   - Пример:
     ```sql
     CREATE USER 'goodrussian'@'localhost' IDENTIFIED BY 'SecurePassword123!';
     GRANT SELECT, INSERT, UPDATE ON goodrussian.* TO 'goodrussian'@'localhost';
     ```

3. **TLS/SSL**
   - Использовать HTTPS для передачи паролей

4. **CSRF Protection**
   - Добавить CSRF-токены в формы
   - Валидировать на сервере

5. **Rate Limiting**
   - Ограничить количество попыток входа
   - Защитить от brute-force атак

---

## Ошибки и решения

### "SQLSTATE[HY000]: General error: 1030 Got error"
- Проверить права доступа на папку `/config`
- Убедиться, что сервер может писать в временные файлы

### "Access denied for user 'root'@'localhost'"
- Проверить пароль в `config/db.php`
- Убедиться, что MySQL запущена

### "Table 'goodrussian.users' doesn't exist"
- Создать таблицу через SQL (см. выше)
- Или открыть `http://localhost:8081/init_db.php`

---

## Файлы структуры

```
goodrussian/
├── config/
│   └── db.php              # Конфигурация БД
├── Account/
│   ├── account.php         # Контроллер регистрации/входа
│   ├── Login.html          # Форма входа
│   ├── Register.html       # Форма регистрации
│   └── ForgotPassword.html # Форма сброса пароля
├── Profile/                # Защищенные страницы (требуют session['user_id'])
├── route.php               # Маршрутизатор (обновлен)
└── package.json
```

---

## Дополнительные возможности

- [ ] Отправка email для сброса пароля
- [ ] Подтверждение email при регистрации
- [ ] Двухфакторная аутентификация
- [ ] OAuth (Google, VK)
- [ ] Логирование попыток входа
- [ ] Управление сессиями
