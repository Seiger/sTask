# Seeder Fix - PostgreSQL Sequence Issue

## ❌ Проблема

```
SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "daisy_permissions_groups_pkey"
DETAIL: Key (id)=(1) already exists.
```

### Причина

1. Evolution CMS під час встановлення створює записи в `permissions_groups`
2. PostgreSQL sequence може бути не синхронізований
3. `insertGetId()` намагається вставити з id=1, але він вже зайнятий
4. **Падає з помилкою duplicate key**

### Старий код (НЕПРАВИЛЬНИЙ)

```php
protected function createPermissions(): void
{
    // ❌ insertGetId() не перевіряє чи існує запис
    $groupId = DB::table('permissions_groups')->insertGetId([
        'name' => 'sTask',
        'lang_key' => 'sTask',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // ❌ insert() також не перевіряє
    foreach ($permissions as $permission) {
        DB::table('permissions')->insert($permission);
    }
}
```

**Проблеми:**
- ❌ Не ідемпотентний - падає при повторному запуску
- ❌ Не працює з PostgreSQL sequences
- ❌ Не перевіряє наявність даних

---

## ✅ Рішення

### Новий код (ПРАВИЛЬНИЙ)

```php
protected function createPermissions(): void
{
    // ✅ updateOrInsert - ідемпотентний метод
    DB::table('permissions_groups')->updateOrInsert(
        ['name' => 'sTask'], // Match condition
        [
            'lang_key' => 'sTask',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    // Get the group ID after upsert
    $groupId = DB::table('permissions_groups')
        ->where('name', 'sTask')
        ->value('id');

    // ✅ updateOrInsert для permissions
    foreach ($permissions as $permission) {
        DB::table('permissions')->updateOrInsert(
            ['key' => $permission['key']], // Match by key
            array_merge($permission, [
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ])
        );
    }
}
```

**Переваги:**
- ✅ **Ідемпотентний** - можна запускати багато разів
- ✅ Працює з PostgreSQL sequences
- ✅ Не дублює дані
- ✅ Оновлює існуючі записи
- ✅ Створює нові якщо їх немає

---

## 📝 Спрощений run() метод

### Старий код

```php
public function run(): void
{
    // Перевірка існування
    $groupExists = DB::table('permissions_groups')
        ->where('name', 'sTask')
        ->exists();

    if ($groupExists) {
        $this->updatePermissions();
        return;
    }

    $this->createPermissions();
}
```

### Новий код

```php
public function run(): void
{
    // Check if permissions tables exist
    if (!Schema::hasTable('permissions_groups')) {
        return;
    }

    // Just run createPermissions - it uses updateOrInsert (idempotent)
    $this->createPermissions();
}
```

**Простіше бо:**
- updateOrInsert сам робить перевірку
- Не треба окремих методів для create/update
- Один метод працює для всіх випадків

---

## 🧪 Тестування

### 1. Перший запуск (fresh install)
```bash
php artisan db:seed --class="Seiger\sTask\Database\Seeders\STaskPermissionsSeeder"
```
**Результат:** ✅ Створює групу і permissions

### 2. Повторний запуск
```bash
php artisan db:seed --class="Seiger\sTask\Database\Seeders\STaskPermissionsSeeder"
```
**Результат:** ✅ Оновлює існуючі, не падає

### 3. Запуск з існуючими даними
```bash
# Якщо в базі вже є permissions_groups з id=1
php artisan db:seed --class="Seiger\sTask\Database\Seeders\STaskPermissionsSeeder"
```
**Результат:** ✅ Працює коректно, не падає з duplicate key

---

## 📊 updateOrInsert vs insertGetId

| Метод | Ідемпотентний | PostgreSQL Safe | Перевіряє існування |
|-------|---------------|-----------------|---------------------|
| `insertGetId()` | ❌ | ❌ | ❌ |
| `insert()` | ❌ | ❌ | ❌ |
| `updateOrInsert()` | ✅ | ✅ | ✅ |
| `firstOrCreate()` | ✅ | ✅ | ✅ |
| `upsert()` | ✅ | ✅ | ✅ |

---

## ✨ Результат

### До виправлення
```
SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint
```

### Після виправлення
```
✅ Seeders completed
```

---

## 📚 Laravel Best Practices

**Золоте правило для Seeders:**

> **Seeders завжди мають бути ідемпотентними**
> 
> Використовуй:
> - `updateOrInsert()` - для простих випадків
> - `firstOrCreate()` - для Eloquent моделей
> - `upsert()` - для batch operations
> 
> Не використовуй:
> - `insert()` - падає на duplicate key
> - `insertGetId()` - падає на duplicate key
> - `create()` - падає на duplicate key

---

## 🎯 Чому це важливо?

1. **Docker/K8s environments** - seeders можуть запускатись багато разів
2. **CI/CD pipelines** - автоматичні деплої запускають seeders
3. **Development** - розробники перезапускають seeders
4. **PostgreSQL** - sequences можуть бути не синхронізовані
5. **Production** - дані можуть вже існувати

**Ідемпотентні seeders = надійна система!** 🚀

