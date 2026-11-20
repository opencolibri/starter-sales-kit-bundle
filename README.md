# opencolibri/starter-sales-kit-bundle

The admin of a sales kit: create demo shops from datasets, generate and load their catalog into Sylius, give each
one its storefront theme and header layout, keep it private for a customer, and browse the datasets. A Symfony bundle
built on [Sylius Shadcn Admin UI](https://github.com/Sylius/ShadcnAdminUi). Each team builds its own sales kit
instance from it: users, branding, colors, domains and extra pages stay in the instance.

![The shops of a sales kit](https://raw.githubusercontent.com/opencolibri/colibri-starter/main/docs/images/sales-kit-shops.webp)

Developed in the [colibri-starter](https://github.com/opencolibri/colibri-starter) monorepo (`src/SalesKitBundle`), split
into the read-only [opencolibri/starter-sales-kit-bundle](https://github.com/opencolibri/starter-sales-kit-bundle) repository. Unlike
the other packages, it is not a Castor plugin but a Symfony bundle. See the
[sales kit section of the documentation](https://starter.opencolibri.org/docs/sales-kit/) for an overview.

## How it works

The admin never changes Sylius itself. A sales kit **stack** runs the shared Sylius app of the demo shops and
[castor-api](https://github.com/jeromegxj/castor-api), which runs Castor tasks over HTTP; the admin calls it for every
action and follows the long ones (generate, load, delete…) with Live components.

```
sales kit admin (this bundle) ──HTTP──▶ castor-api ──▶ Castor tasks ──▶ shared Sylius app (one channel per shop)
            └──────────── reads and writes the shared .castor/import directories ────────────┘
```

## What the stack provides

| What | Used for |
|---|---|
| The `sylius:shop:*` tasks of `opencolibri/starter-sales-kit` | shops (list, create, generate, load, delete, theme, visibility), with a theme, a private storefront and an admin each |
| The `sylius:dataset:*` tasks of `opencolibri/starter-fixtures` and `opencolibri/starter-dataset-builder` | datasets (list, build, images, export, delete) |
| `sales-kit:theme:list` (JSON: `themes`, and the `headers` of `HeaderLayout::all()`), `sales-kit:theme:header` | thin `#[AsApi]` tasks over `ThemeBundle::pickable()` and `sylius:theme:header` |
| Every theme installed with the theme-bundle target (`castor sylius:theme:setup all --target=theme-bundle`) | the theme picker |
| The `.castor/import/var`, `.castor/import/exports` and `.castor/import/datasets` directories, mounted in the admin | shop images and `project.yaml`, run progress, dataset zips, catalogs of the datasets it built (read only) |

## What the package ships

| Part | Where |
|---|---|
| castor-api client, run descriptions | `src/Castor/` |
| Shop images and `project.yaml` storage, run progress | `src/Import/` |
| Pages: dashboard figures, shops, datasets and their catalog, theme and dataset thumbnails, Caddy on-demand TLS endpoint | `src/Controller/`, `src/Dataset/`, `templates/` |
| Shop and dataset lists: PHP Sylius grids, filtered, sorted and paginated in memory | `src/Grid/`, `src/Repository/` |
| Live components: `SalesKit:RunMonitor`, `ShopActions`, `ShopVisibility`, `ThemePicker`, `HeaderLayoutPicker`, `DeleteShop` | `src/Twig/Components/` |
| Anonymous components: `sales_kit:action_button`, `sales_kit:message`, `sales_kit:header_preview` | `templates/components/ui/` |
| Sidebar items (Dashboard, Shops, Datasets) | `src/Menu/AdminMenuBuilder.php` (decorates `sylius_admin_ui.knp.menu_builder`) |
| Hooks of every page, plus the Shadcn Admin UI hooks | `config/app.php` |
| Tailwind sources of the templates | `assets/styles/sales-kit.css` |

Login, logout, the layout, the sidebar, the dark mode and the color themes come from Sylius Admin UI and Shadcn Admin
UI. The theme and dataset thumbnails come from `opencolibri/starter-themes` and `opencolibri/starter-datasets`.

## Install it in an instance

An instance is a Symfony app (AssetMapper, Tailwind) next to a sales kit stack. `sylius/shadcn-admin-ui` has no
release yet: allow its development version next to the package.

```bash
composer require opencolibri/starter-sales-kit-bundle:^0.1@dev sylius/shadcn-admin-ui:^0.14@dev
```

Flex enables the bundles. Then:

1. **Hooks** of the pages, then your own below the import (they win):
   ```yaml
   # config/packages/opencolibri_sales_kit.yaml
   imports:
       - { resource: '@OpenColibriStarterSalesKitBundle/config/app.php' }

   opencolibri_sales_kit: ~
   ```
2. **Routes:**
   ```yaml
   # config/routes/sales_kit.yaml
   sylius_admin_ui:          # login, logout, dashboard
       resource: '@SyliusAdminUiBundle/config/routes.php'
   opencolibri_sales_kit:    # shops, datasets, thumbnails, Caddy endpoint
       resource: '@OpenColibriStarterSalesKitBundle/config/routes.php'
   live_component:
       resource: '@LiveComponentBundle/config/routes.php'
       prefix: '/_components'
   ```
3. **Firewall** on the Sylius Admin UI login routes (a recipe cannot merge `security.yaml`):
   ```yaml
   # config/packages/security.yaml
   security:
       password_hashers:
           Symfony\Component\Security\Core\User\InMemoryUser: auto
       providers:
           admin:
               memory:
                   users: '%admin_users%'   # or any user provider
       firewalls:
           main:
               lazy: true
               provider: admin
               form_login:
                   login_path: sylius_admin_ui_login
                   check_path: sylius_admin_ui_login_check
                   default_target_path: sylius_admin_ui_dashboard
                   enable_csrf: true
                   csrf_parameter: _csrf_admin_security_token
                   csrf_token_id: admin_authenticate
               logout:
                   path: sylius_admin_ui_logout
                   target: sylius_admin_ui_login
       access_control:
           - { path: ^/_caddy/on-demand-ask, roles: PUBLIC_ACCESS }
           - { path: ^/login, roles: PUBLIC_ACCESS }
           - { path: ^/, roles: ROLE_USER }
   ```
4. **Styles:** `assets/styles/admin.css`, listed in `symfonycasts_tailwind.input_css`:
   ```css
   @import "tailwindcss";
   @import "../../vendor/sylius/shadcn-admin-ui/assets/styles/theme.css";
   @import "../../vendor/opencolibri/starter-sales-kit-bundle/assets/styles/sales-kit.css";
   ```
5. **JavaScript:** an `admin` entrypoint in `importmap.php` (`./assets/admin.js`) that starts Stimulus
   (`import { startStimulusApp } from '@symfony/stimulus-bundle'; startStimulusApp();`), with the `live` controller of
   `@symfony/ux-live-component` enabled in `assets/controllers.json`.
6. **Environment:** see the configuration below, and mount the `.castor/import` directories of the stack.

## Configuration

Every option defaults to an environment variable:

```yaml
opencolibri_sales_kit:
    castor_api:
        url: '%env(CASTOR_API_URL)%'
        token: '%env(default::CASTOR_API_TOKEN)%'           # empty: no authentication
    shop_root_domain: '%env(SHOP_ROOT_DOMAIN)%'             # shops on {slug}.<domain>
    import_var_dir: '%env(IMPORT_VAR_DIR)%'                 # .castor/import/var of the stack
    import_export_dir: '%env(IMPORT_EXPORT_DIR)%'           # .castor/import/exports of the stack (dataset zips)
    import_datasets_dir: '%env(default::IMPORT_DATASETS_DIR)%'  # .castor/import/datasets, read only: catalogs of the datasets built by the stack
```

## Customizing an instance

- **Branding:** `sylius_admin.common.component.sidebar.logo`, `sylius_admin.security.login.page.logo` and
  `sylius_admin.base#base_title` hooks.
- **Colors:** `sylius_shadcn_admin_ui.themes` (e.g. `[stone, rose]`), or shadcn tokens in `admin.css`.
- **Blocks on a page:** each page renders its own hook plus `opencolibri_sales_kit.common.page`. Add a hookable to
  `opencolibri_sales_kit.shop.show.content.body.aside` (or `.main`, `.main.storefront`, `.content.body` of any page),
  disable one with `enabled: false`, or replace its template. The page data is in `hookable_metadata.context` (see the
  table below). A context value that is null is left out: read it with `|default(null)`.
- **Grids:** `ShopGrid` and `DatasetGrid` (`src/Grid/`) are PHP Sylius grids (`#[AsGrid]`), so an instance changes
  them like any Sylius grid. castor-api returns every shop or dataset in one call: the grid providers turn the criteria,
  sorting and page into calls on `ShopRepository` / `DatasetRepository` (immutable in-memory queries:
  `withSearch()`, `withTheme()`, `orderBy()`, `paginate()`…), loaded once per request.
- **Pages:** controllers of the instance, and a decorator of `sylius_admin_ui.knp.menu_builder` for the sidebar.

| Page hook | Context |
|---|---|
| `opencolibri_sales_kit.shop.index` | `resources` (grid view) |
| `opencolibri_sales_kit.shop.new` | `form`, `datasets` |
| `opencolibri_sales_kit.shop.show` | `shop`, `projectForm`, `themes`, `theme_headers` |
| `opencolibri_sales_kit.dataset.index` | `resources` (grid view) |
| `opencolibri_sales_kit.dataset.show` | `dataset`, `catalog` (null when its files are not readable), `collection`, `shops` |
| `opencolibri_sales_kit.dataset.new` | `form`, `source` |
| `sylius_admin.dashboard.index` | — |

## Tests

```bash
vendor/bin/phpunit --testsuite sales-kit    # from the monorepo root
```

`tests/Application/Kernel.php` is a sales kit instance with an `admin` user, where castor-api is replaced by
`tests/Support/FakeCastorApi.php`: the list tasks answer the JSON of `tests/Fixtures/castor-api/`, every call is
recorded, and `fail()` / `conflict()` make a task fail or hold its lock. No sales kit stack is needed. The tests cover
the in-memory repositories, the dataset catalogs, the castor-api client, the pages (`WebTestCase`) and the Live and
Twig components (`InteractsWithLiveComponents`, `InteractsWithTwigComponents`).

## Good to know

- Inside a `<twig:…>` component, `this` is that component and macros imported by the template are not visible: read
  the data and render the hooks before it. Shared bits are anonymous components (`<twig:sales_kit:action_button>`,
  `<twig:sales_kit:message>`).
- Never `{% set icon = ux_icon(...) %}` then `{{ icon }}`: `ux_icon()` returns a plain string and is only marked safe
  where it is printed, so the variable is escaped. Print it directly, or capture it with `{% set icon %}…{% endset %}`
  (a `Markup`, safe everywhere, including inside the blocks of another component).
- The admin is in English only for now.
