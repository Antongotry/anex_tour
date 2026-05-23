# Anex Tour — архітектура API і статичного шару

## Шари

| Шар | Де | Навіщо |
|-----|-----|--------|
| **Live API** | `hotel-catalog.php`, shortcodes | Ціни, дати, наявність, пошук |
| **Статичний CPT** | `anex_hotel`, `anex_tour` | Назви, країни, фото, SEO-картки без search-list на кожен візит |
| **Sync (admin)** | Sync готелів / Sync турів | Контрольований імпорт, ліміт API |
| **REST** | `anex_tour` → `/wp-json/wp/v2/anex-tours` | Підключення каталогу до CPT (наступний етап) |

## Готелі (`anex_hotel`)

1. `module/params` → регіони  
2. `module/params/destinations` (часто 0 готелів)  
3. Fallback: **`module/search-list`** (1 запит / країна, вікно дат 12 днів)  
4. Фото: офер → **`tour/info/{key}`** (`hotel_info.images`, до 20), full URL  
5. Не використовувати на цьому токені: `hotel/{id}/hotel-images` (forbidden)

## Тури / екскурсії (`anex_tour`)

1. **`module-excursion/search`** по whitelist країн (2 вікна дат + fallback без `transport_type`)  
2. Унікальний ID: поле **`key`** (для `tour-excursion/info/{key}`)  
3. Фото: `country_images` з офера + `tour-excursion/info` (`limit_images`)  
4. Обмеження API: часто **1–N турів на країну** у видачі — це не баг sync

## Ліміти API

- Пріоритет: не дублювати `module/search-list` на фронті для кожного гостя  
- Sync: пакетами по країнах, лог у admin  
- Кеш: `includes/api.php` (transients для search / tour info)

## Деплой

- Git: `https://github.com/Antongotry/anex_tour` → `main`  
- Hostinger: `domains/anextour.agency/public_html/wp-content/plugins/anex-tour`  
- SSH: `u356147021@92.112.182.50:65002`  
- WP-CLI: `wp eval-file wp-content/plugins/anex-tour/includes/cli-*.php`

## Наступні кроки

- [ ] Каталог: режим «з CPT» для карток готелів/турів + live ціни по `hotel_id` / `tour_key`  
- [ ] Phase 0: зменшити search-list на публічних сторінках  
- [ ] Окремий sync-фото для турів (batch) як у готелів  
