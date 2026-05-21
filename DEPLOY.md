# Hostinger Git Deploy — швидка шпаргалка

## anextour.agency

```
Репозиторій: https://github.com/Antongotry/anex_tour.git
Гілка:       main
Каталог:     domains/anextour.agency/public_html/wp-content/plugins/anex-tour
```

## Перший раз (якщо плагін уже на сервері)

1. SSH або File Manager: `mv anex-tour anex-tour.backup-YYYYMMDD`
2. `mkdir anex-tour` (порожня)
3. Hostinger Git → Створити → вказати поля вище → **Створити**
4. Перевірити сайт, Elementor-шорткоди, картку екскурсії

## Що НЕ в Git

- `.env` / паролі SSH
- Токен API IT-Tour (тільки в WP options)
- `wp-content/mu-plugins/ittour-lab` — окремий модуль, не цей репо
