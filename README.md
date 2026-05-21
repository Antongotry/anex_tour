# Anex Tour — WordPress plugin

Плагін пошуку турів, каталогу готелів/екскурсій і шорткодів для Elementor (Anex Tour).

**Репозиторій:** [github.com/Antongotry/anex_tour](https://github.com/Antongotry/anex_tour)

## Що в репозиторії

Тільки код плагіна (корінь репо = папка `anex-tour` на сервері):

- `anex-tour.php` — головний файл плагіна
- `includes/` — API, шорткоди, адмінка, бронювання
- `templates/` — шаблони каталогу та віджетів

На продакшені **не** деплоїться весь WordPress — лише ця папка в `wp-content/plugins/anex-tour/`.

## Автодеплой Hostinger (Git)

У hPanel → **Git** → «Створити новий репозиторій»:

| Поле | Значення |
|------|----------|
| **Репозиторій** | `https://github.com/Antongotry/anex_tour.git` |
| **Гілка** | `main` |
| **Каталог** | `domains/anextour.agency/public_html/wp-content/plugins/anex-tour` |

Важливо:

1. У **Каталозі** має бути саме шлях до **папки плагіна**, не `public_html` і не корінь сайту.
2. Hostinger вимагає **порожню** цільову папку при першому деплої — зробіть бекап, перейменуйте стару `anex-tour` у `anex-tour.bak`, створіть порожню `anex-tour`, потім запустіть деплой.
3. Після деплою в WordPress перевірте, що плагін **Anex Tour Widget** активний.
4. Токен IT-Tour API задається в **Налаштування → Anex Tour** (у Git не зберігається).

## Локальна розробка

Скопіюйте вміст репо в:

`your-site/wp-content/plugins/anex-tour/`

## Оновлення

```bash
git add .
git commit -m "опис змін"
git push origin main
```

Потім у Hostinger — **Pull / Deploy** (або автоматично, якщо увімкнено webhook).
