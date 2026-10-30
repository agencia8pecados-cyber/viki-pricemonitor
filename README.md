# viki-pricemonitor

Módulo `pricemonitor`: vigila los precios de la competencia en Google Shopping mediante SerpApi, con semáforo visual y ajuste de precio.

## Qué hace

Busca cada producto por EAN o referencia, guarda los precios de la competencia, excluye el dominio propio y clasifica cada producto como caro, correcto o barato según dos umbrales. Desde el panel se puede crear una rebaja (`specific_price`).

## Arquitectura

- `pricemonitor.php`: instalación, hooks de back office (`displayAdminProductsExtra`, `actionAdminControllerSetMedia`, `displayBackOfficeHeader`).
- `classes/SerpApiClient.php`: cliente de la API.
- `controllers/admin/`: panel (`AdminPriceMonitorDashboard`) y ajustes (`AdminPriceMonitorSettings`).
- `cron/scan.php`: escaneo por lotes, protegido por token.
- Tablas: `pricemonitor_competitor`, `pricemonitor_exclusion`, `pricemonitor_log`, `pricemonitor_scan`.

## Instalación en otro PrestaShop

Sube `pricemonitor/` a `modules/` e instálalo. PS 1.7.0 a 8.x. Configura la clave de SerpApi y programa el cron con la URL que muestra el panel.

## Configuración

Ajustes `PRICEMONITOR_*`: `API_KEY`, `COUNTRY`, `LANG`, `OWN_DOMAIN`, `USE_EAN`, `USE_REFERENCE`, `THRESHOLD_CHEAP`, `THRESHOLD_EXPENSIVE`, `CRON_TOKEN`, `CRON_BATCH_SIZE`, `CRON_DELAY`, `CRON_DAY_START`, `CRON_DAY_END`, `RETENTION_DAYS`.

## Dependencias

API de SerpApi, de pago por búsqueda. Sin dependencias de otros módulos.

## Núcleo y específico

Módulo genérico. Lo específico es la configuración en BD. Queda `goalkers.com` en un comentario de uso de `cron/scan.php` y como ejemplo en el texto de ayuda de ajustes.

## Pendientes

- Quitar las referencias a goalkers.com del código.

---

© 8Pecados, todos los derechos reservados. Repositorio privado.
