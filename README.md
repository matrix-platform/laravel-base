# matrix-platform/laravel-base

Laravel 後台引擎。用一行字串 DSL 描述欄位,換到一整套後台 CRUD:清單、篩選、排序、分頁、表單驗證、複製、拖曳排序、匯出、稽核軌跡、以及以選單樹為基礎的授權模型。

```php
class WidgetController extends CrudController {

    protected string $model = Widget::class;

    protected array $lists = ['id', 'title', 'status:select', 'category.title=category_title', 'create_time'];

    protected array $forms = ['title', 'status:select', 'category_id:select', 'content:text'];

}
```

上面這個類別會生出十個端點、一份帶型別與驗證規則的欄位描述、以及一組可以逐項授權的權限點。


---

## 目錄

- [套件不提供什麼](#套件不提供什麼)
- [安裝](#安裝) —— 從 `composer require` 到能登入
- [五個必讀概念](#五個必讀概念)
- [開一個自己的功能](#開一個自己的功能)
- [前台身分（member / vendor）](#前台身分member--vendor) —— 套件只給零件
- [訊息](#訊息) —— [送訊息](#送訊息)、[派送與 worker](#派送與-worker)、[後台查詢、重發與取消](#後台查詢重發與取消)、[Push 訂閱(Member)](#push-訂閱member)、[Telegram 綁定與 Webhook](#telegram-綁定與-webhook)、[自訂訊息通道](#自訂訊息通道)
- [參考](#參考) —— [端點](#端點)、[設定鍵](#設定鍵)、[cfg 設定鍵](#cfg-設定鍵)、[主控台指令](#主控台指令)、[錯誤代碼](#錯誤代碼)、[資料表](#資料表)
- [給前端](#給前端) —— [請求形狀](#請求形狀)、[回應形狀](#回應形狀)、[語系](#語系)、[`api/common/page`](#apicommonpage)、[驗證碼](#驗證碼)、[傳輸加密](#傳輸加密)
- [沿用套件的 lint](#沿用套件的-lint) —— 選用,共用同一套風格檢查([style-check 檢查什麼](#style-check-檢查什麼))
- [已知限制與取捨](#已知限制與取捨) —— [安全](#安全)、[規模與效能](#規模與效能)、[資料生命週期](#資料生命週期)、[行為細節](#行為細節)
- [從舊版升級](#從舊版升級)

---

## 套件不提供什麼

先講界線,因為這些是刻意的決定,不是還沒做:

| 沒有 | 說明 |
|---|---|
| **前台登入端點** | 套件出貨 `member-api` / `vendor-api` middleware 與 `AuthToken::issue()`,**但沒有任何前台登入 controller**。前台的登入流程、驗證碼策略、密碼規則由宿主決定 |
| **API 文件產生器** | 不出貨 Swagger / OpenAPI。端點是 `#[Action]` 反射掛載的,要文件請從 attribute 反射產生,不要掃註解 |
| **排程註冊** | 套件不呼叫 `Schedule::command()`。`matrix:prune-tokens`、`matrix:prune-drive-files` 與 `messages:dispatch` 都由宿主自己排(見[註冊排程](#8-註冊排程如果要用訊息或-token-清理)) |
| **cache / queue driver 的選擇** | 套件用 `Cache` 與 `Queue` 門面,不指定 driver。驗證碼要跨請求共用的 cache,訊息派送要每條 queue 恰好一個 worker（見[派送與 worker](#派送與-worker)） |
| **匯入** | 匯出有,匯入沒有 |
| **檔案清理** | 一般上傳的 `base_file` 與磁碟上的檔案永遠不會被自動刪除,去重讓一筆記錄可能被多處引用,套件答不出「誰可以刪」。**例外**:drive-linked 的 `base_file`(`path` 以 `@` 開頭,對應雲端硬碟欄位 `drive-file`/`drive-image` 選檔後產生的連結)由 `matrix:prune-drive-files` 排程時即時掃描 CRUD 資料表,清掉沒有任何記錄引用的部分 |
| **多資料庫支援** | 只支援 PostgreSQL,而且是硬性的（見下一節） |

---

## 安裝

**照順序走,每一步都有驗證動作。** 中間任何一步失敗,不要往下走 —— 這套件的失敗多半是靜默的。

### 1. 環境

| 需求 | 為什麼 | 不滿足會怎樣 |
|---|---|---|
| **PostgreSQL** | 主鍵與排序值來自兩個共用 sequence（`CREATE SEQUENCE` / `NEXTVAL`）、權限與稽核用 `jsonb`、`base_operator` 是 `CREATE OR REPLACE VIEW` | `php artisan migrate` 第一個 migration 的第一行就 SQL 語法錯誤 |
| PHP 8.3+ | —— | composer 會擋 |
| Laravel 13.23+ | `composer.json` 宣告 `^13.23`,那也是 `--prefer-lowest` 實際跑過完整測試的版本;低於它的 13.x 沒有驗證過 | composer 會擋 |
| `ext-pdo_pgsql` | 上面那條的驅動,由 `composer.json` 強制檢查 | composer 會擋 |
| `ext-gd`，**且編譯時帶 FreeType**（**只有**把 `matrix.admin-captcha-provider` 設成 `captcha-image` 才要,預設不要） | 圖形驗證碼用 `imagettftext()` 畫字 | `admin/auth/captcha` 回 500,而驗證碼開著的時候登入一定要 code —— 完全登不進去。裝不了 GD 就改用 `captcha-turnstile` / `captcha-recaptcha`,或維持預設的 `captcha-none` |
| 一個**跨請求共用**的 cache store（同上,只有 `captcha-image` 才要） | 驗證碼答案寫在 cache,下一個請求才比對 | `CACHE_STORE=array` 的話每次登入都是 `invalid-captcha`。多台機器沒有共用 cache 會隨機失敗 |

**驗證:**

```bash
php -r 'exit(function_exists("imagettftext") ? 0 : 1);' || echo 'GD 缺少 FreeType,後台將完全登不進去'
```

`composer.json` 只能要求 `ext-gd` 存在,擋不掉「有 GD 但沒有 FreeType」。這一條與 cache store 那一條都要到**第一次有人登入**才會爆,所以請把上面那行放進部署腳本或 CI —— 它回非零 exit code。

### 2. 安裝套件

```bash
composer require matrix-platform/laravel-base
```

服務提供者會自動註冊（`extra.laravel.providers`）。migration 與路由自動載入,**不需要也不能發佈**。

### 3. 建立 `config/matrix.php`

**這個檔案一定要自己建。** 套件的預設值走 `mergeConfigFrom`,只合併**頂層** key —— 你宣告 `messaging` 就會整個取代掉套件的 `messaging`（包含它底下的 `channels`）。所以要嘛整份照抄,要嘛只宣告你真的要改的頂層 key,並把該 key 的完整內容一起寫上。

完整範本（等同套件出貨值）:

```php
<?php

use MatrixPlatform\Models\MailLog;
use MatrixPlatform\Models\Member;
use MatrixPlatform\Models\SmsLog;
use MatrixPlatform\Models\Vendor;

return [

    'admin-api-prefix' => 'admin',

    'admin-menus' => 'base',

    'api-prefix' => 'api',

    'file-private-disk' => 'local',

    'file-public-disk' => 'public',

    'locales' => 'tw en',

    'member-model' => Member::class,

    'messaging' => [
        'channels' => [
            'mail' => ['model' => MailLog::class, 'queue' => 'messaging-mail'],
            'sms' => ['model' => SmsLog::class, 'queue' => 'messaging-sms'],
        ],
    ],

    'packages' => 'app base',

    'resource-cfg' => [],

    'resource-i18n' => [],

    'resource-i18n-menu' => [],

    'resource-i18n-model' => [],

    'resource-i18n-options' => [],

    'resource-i18n-template' => [],

    'vendor-model' => Vendor::class,

];
```

**語系要對得起來。** `locales` 列出的每一個值,都必須在 `resources/i18n/{語系}/` 底下有對應目錄。Laravel 預設的 `APP_LOCALE=en` 可以直接用;若你設成 `zh_TW` 之類的值,套件找不到 `resources/i18n/zh_TW/`,**整個後台的標題、選單、錯誤訊息會退化成原始 token**（畫面看起來還在跑,只是字都變成 `errors.permission-denied` 這種東西）。

### 4. 跑 migration

```bash
php artisan migrate
```

套件的 migration 檔名是 `0001_` 到 `0007_`,字串排序落在 Laravel 內建的 `0001_01_01_*` 之後、你自己的日期式 migration 之前。**你自己的表如果要用套件的主鍵慣例,順序不能反過來** —— `0001_foundation` 建立 `base_id` 與 `base_ranking` 兩個 sequence,你的 migration 必須排在它後面。

**驗證:** `base_user`、`base_group`、`base_auth_token` 等 17 張表存在。

### 5. 跑 seeder

套件出貨兩個 seeder,**都不會自動執行**,要在你自己的 `DatabaseSeeder` 裡呼叫:

```php
public function run(): void {
    $this->call(\MatrixPlatform\Database\Seeders\UserSeeder::class);
    $this->call(\MatrixPlatform\Database\Seeders\CitySeeder::class);   // 台灣縣市與行政區,不需要就別跑
}
```

`UserSeeder` 建兩個帳號:`root@matrix`（id = 1）與 `admin`（id = 2）。**兩個都沒有密碼**,而且 id 是寫死的 —— 那是權限模型的一部分（見[五個必讀概念](#五個必讀概念)）。

`CitySeeder` 寫入 394 列,而且**每一列都會產生一筆稽核紀錄** —— 這是刻意的,稽核軌跡要答得出「這批資料是誰在什麼時候放進來的」。

**驗證:** `base_user` 有 id 1 與 2 兩列。

### 6. 設定管理員密碼

```bash
php artisan matrix:passwd root@matrix
```

指令會問兩次密碼。密碼規則來自 `cfg('admin.password-pattern')`,出貨值是「至少 8 碼、含英文與數字」。同一規則也套用在登入後自行改密碼與後台使用者表單的非空密碼;重設成功會撤銷該帳號所有既有 session。

**這是建立管理員的唯一官方入口。** 後台介面建出來的帳號一律是一般使用者 —— 因為 id 來自從 10000000 起跳的共用 sequence,而管理員等級是由 id 區間決定的。

**驗證:** 下一步能登入。

### 7. 打第一個請求

**先產生加密金鑰圈:**

```bash
php artisan matrix:rotate-encryption-key
```

`matrix.admin-api-encryption` 與 `matrix.vendor-api-encryption` 出貨是**開的**,金鑰圈空著的時候 `encryption-key` 只會回 `encryption-unavailable`,整個後台開不起來。

**然後下面這兩個 curl 還是會失敗**——加密開著的時候明文請求一律回 `invalid-envelope`,`auth/captcha` 與 `auth/login` 沒有宣告 `#[Action(encrypted: false)]`。要用 curl 驗證,先在你的 `config/matrix.php` 把 `'admin-api-encryption' => false` 關掉;要驗證加密路徑,用瀏覽器端(前端 `initConfig` 的 `encryption` 也是預設開的),或照[給前端](#傳輸加密)那節的線路格式自己組信封。

```bash
# 1. 取驗證碼(匿名)
curl -X POST http://localhost/admin/auth/captcha
# → {"success":true,"data":{"token":"...","image":"data:image/png;base64,..."}}

# 2. 登入(把圖片裡的字讀出來當 code)
curl -X POST http://localhost/admin/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"root@matrix","password":"...","token":"上一步的 token","code":"圖片裡的字"}'
# → {"success":true,"data":{"token":"..."}}
```

**驗證:** 拿到 token。用它打 `POST admin/auth/profile` 應該回傳選單樹與個人資料。

### 8. 註冊排程(如果要用訊息或 token 清理)

套件不註冊排程。在你的 `routes/console.php`:

```php
Schedule::command('matrix:prune-tokens')->daily()->withoutOverlapping();
Schedule::command('matrix:prune-drive-files')->daily()->withoutOverlapping();
Schedule::command('messages:dispatch')->everyMinute()->withoutOverlapping();
```

`withoutOverlapping()` 不是裝飾:兩個 prune 行程同時跑會互相搶同一批列。

訊息還需要 queue worker。mail、sms、push、telegram 各有自己的 queue,而**每條 queue 只能有一個 worker**:

```
php artisan queue:work --queue=messaging-mail
php artisan queue:work --queue=messaging-sms
php artisan queue:work --queue=messaging-push
php artisan queue:work --queue=messaging-telegram
```

worker 會睡掉供應商的 `interval` 來節流,所以 `--timeout` 與 connection 的 `retry_after` 都必須大於最大的 `interval`。這三個數字沒對齊、或漏開一條 worker 的後果與訊號,見[派送與 worker](#派送與-worker)。

---

## 五個必讀概念

### 1. 回應永遠是 HTTP 200,狀態碼在 body 裡

```json
{ "success": true,  "data": { } }
{ "success": false, "code": 404, "error": "data-not-found", "message": "查無資料" }
{ "success": false, "code": 422, "error": "validation-failed", "message": "...", "fields": { "username": ["required"] } }
```

這不是風格偏好:客戶端的防火牆會擋掉非 200 的回應。`error` 欄位就是 i18n key,`message` 是後端已經翻好的字串 —— 前端可以直接顯示 `message`,也可以拿 `error` 自己對照。**唯一的例外是 `GET api/files/{path}`**——它要直接當 `<img src>` 或瀏覽器重定向目標用,回真正的 302/404,不走信封(見「端點」表)。

**信封的範圍是套件的兩個路由前綴。** `admin/` 與 `api/` 底下打不到的路徑、用錯方法的請求,都會回信封格式的 404,**`GET api/files/{path}` 除外**——它是刻意不信封化的公開檔案讀取端點。**這兩個前綴之外的請求不受影響** —— 你自己的網頁 404 還是 Laravel 原本的樣子。

代價要知道:因為兜底路由吃下所有方法,**「方法用錯」不再回 405,而是回 404 信封**。前端分不出「網址拼錯」與「方法用錯」。

### 2. 全部端點都是 POST

除了 `GET api/files/{path}`(公開檔案讀取,詳見「端點」表)之外,其餘沒有一個 GET。讀取(清單、詳情)也是 POST。用 GET 打其他端點會拿到 404 信封。

### 3. 身分、middleware,以及順序

| 別名 | 作用 |
|---|---|
| `encrypted-api` | 解開/封回加密信封,只作用在 `admin` 前綴。**必須掛在 `envelope-api` 之前**,否則錯誤信封不會被加密 |
| `envelope-api` | 把例外收斂成信封。**必須掛在 `encrypted-api` 以外的最外層** |
| `locale-api` | 讀 `Matrix-Locale` header 決定語系 |
| `user-api` | 後台身分,解析 token,失敗回 401 `invalid-token` |
| `permission-api` | 授權,**必須掛在 `user-api` 之後** |
| `member-api` / `vendor-api` | 前台身分 |
| `member-aware-api` | 有 token 就解析,沒有也放行 |
| `login-throttle-api:{bundle}` | 登入節流,鍵是 `IP + 帳號` |

**順序是約束,不是機制保證。** 把 `permission-api` 掛在 `user-api` 前面不會有人阻止你,但授權會先跑,拿到的是 401 而不是 403。

token 兩種帶法:`Authorization: Bearer {token}`,或登入時自動下的 `matrix-user` cookie。cookie 是 `httpOnly`,`secure` 跟隨 `config('session.secure')` —— 本機開發走 HTTP 時那個值必須是 false,否則瀏覽器不會回送,症狀是「登入成功但下一個請求就 401」。跨網域的前端要送 `credentials: 'include'`,而且 CORS 要自己設(套件的前綴不在 Laravel 預設的 `cors.paths` 裡)。

### 4. 選單即權限

**選單樹不只是導覽,它就是權限模型本身。**

```php
// resources/menu/{bundle}.php —— 縮排跟著選單層次,不是格式錯誤
'widget' => ['icon' => 'fa-solid fa-cube', 'ranking' => 100, 'parent' => 'catalog', 'group' => true, 'tag' => 'query'],

    'widget/{id}' => ['parent' => 'widget', 'tag' => 'query'],
    'widget/{id}/update' => ['parent' => 'widget', 'tag' => 'update'],
    'widget/delete' => ['parent' => 'widget', 'tag' => 'delete'],
    'widget/insert' => ['parent' => 'widget', 'tag' => 'insert'],
    'widget/new' => ['parent' => 'widget', 'tag' => 'insert'],
```

三條規則:

1. **key 就是路由 URI**(去掉 `admin/` 前綴),逐字相同,含 `{id}` 佔位符。
2. **`group => true` 的節點是授權單位**,它底下子節點的 `tag` 匯總成可以授予的動作。
3. **每一個端點都必須有對應的節點。漏一個,那個端點對所有人回 403 —— 包含 ROOT。**

權限值存在 `base_user.permissions` 與 `base_group.permissions` 兩個 jsonb 欄位,形狀是 `{"widget": {"query": true}}`。

**等級寫在主鍵裡:**

| id | 等級 | 能做什麼 |
|---|---|---|
| 1 | Root | 全部,包含標記 `system` 的功能 |
| 2 – 1000 | Admin | 除了 `system` 之外全部,不看 `permissions` |
| 10000000 起 | Regular | 只看 `permissions` |

共用 sequence 從 10000000 起跳,所以**後台介面建出來的帳號一律是 Regular** —— 等級不可能被提權改掉,因為它不是一個欄位。管理員只能用 seeder 或手動指定 id 建立。

帳號管理也遵守同一個階層:Root 可以管理所有帳號;Admin 可以管理 Admin 與 Regular,但看不到 Root;Regular 即使取得 `user` 權限,也只能管理其他 Regular。超出可管理範圍的帳號在清單中不會出現,讀取、更新與刪除則回 `data-not-found`;任何帳號都不能刪除自己。

### 5. 資源疊層:你的檔案覆蓋套件的

設定、翻譯、選單都走同一套疊層。`config('matrix.packages')` 出貨 `'app base'`,`app` 就是你的 Laravel 專案,**排前面的覆蓋排後面的**。

| 放什麼 | 路徑（相對於專案根目錄） |
|---|---|
| 設定 | `resources/cfg/{bundle}.php` |
| 設定的欄位型別（給資源後台用） | `resources/style/cfg/{bundle}.php` |
| 選單 | `resources/menu/{bundle}.php` |
| 選單的標題 | `resources/i18n/{語系}/menu/{bundle}.php` |
| 資料表欄位的標題 | `resources/i18n/{語系}/model/{資料表名}.php` |
| 下拉選項 | `resources/i18n/{語系}/options/{name}.php` |
| 錯誤訊息 | `resources/i18n/{語系}/errors.php` |
| 訊息樣板 | `resources/i18n/{語系}/template/{name}.php` |

bundle 一律**扁平**:只有一層 key,key 本身可以含點,取值時當字面看待,不做巢狀路徑解析。

`resources/style/cfg/{bundle}.php` 只是**欄位型別表**,不是欄位白名單——沒被它列到的 key 照樣會出現在資源後台,型別退回 `text`、呈現退回 `plain`。唯一的例外是標記了 `'secret' => true` 的 key:

```php
'password' => ['type' => 'text', 'presentation' => 'password', 'secret' => true],
```

機密欄位的實際值**永遠不離開伺服器**——`data` 回傳的是遮罩 `••••••••`(沒設定過則是空字串),`default` 與 `placeholder` 一律空白。寫回時遮罩值代表「不變更」,送新值才覆寫,送空字串才清除。出貨已標的是 `gmail.password`、`telegram.bot-token`、`telegram.webhook-secret`、`webpush.private-key`、`mitake.password`、`google-translate.api-key`、`ip2location-bin.download-token`、`ip2location-webservice.api-key`、`captcha-recaptcha.secret`、`captcha-turnstile.secret`;自訂 bundle 裡的憑證要自己標,**漏標的症狀是那把密鑰以明文出現在後台 API 回應裡**。

這一層跟 `matrix.resource-cfg` 的 bundle 白名單是兩件事:白名單決定「這個 bundle 能不能編輯」,`secret` 決定「bundle 裡哪些 key 的值看得到」。有了後者,含憑證的混合型 bundle 才能安全地進白名單。

合併是**逐 key 遞迴**的（排前的覆蓋排後的、key 對 key）,所以覆蓋一個選單節點時,低優先層那個節點多出來的 key（`icon`、`tag` 之類）會滲進合併結果,沒辦法個別移除。要**整個移除**一個節點,把該 key 的值設成 `null` —— 節點消失、端點對所有人 403、群組離開權限樹。但移除節點只擋端點與**新**授權:**已經授出的權限仍然有效**,因為授權檢查讀的是 `base_user.permissions` / `base_group.permissions` 存的 JSON,不是選單樹。

---

## 開一個自己的功能

以一個 `Widget` 為例,**六個步驟,少一個都不會動**。

> **資料表已經 migrate 好的話,可以先跑 `matrix:make-crud {table}` 偷懶**:它會反向產生步驟 2、3、4 的 Model/Declaration/Controller 草稿,以及步驟 6 欄位標題用的 `resources/i18n/{locale}/model/{table}.php`。它是輔助草稿工具,不是黑盒——呈現方式、`--title`、`--parent` 猜不到或猜錯風險高的地方一律留白或印出警示,產生後務必自己核對一遍再送出,路由/選單/選單翻譯仍要照下面步驟 5、6 手動加。細節看[主控台指令](#主控台指令)。

### 1. 資料表

```php
Schema::create('base_widget', function (BaseBlueprint $table) {
    $table->primaryKey();                    // 抽 base_id 共用序列
    $table->text('title');
    $table->integer('category_id')->nullable();
    $table->ranking();                       // 抽 base_ranking 共用序列,要拖曳排序才需要
    $table->schedules();                     // enable_time / disable_time,需要上下架才用
    $table->auditings();                     // create_time / update_time / creator_id / updater_id
});
```

型別提示寫 `BaseBlueprint` 而不是 `Blueprint`,否則靜態檢查看不到那幾個自訂方法。

**主鍵一定要走 `primaryKey()`。** 用 `$table->id()` 會拿到從 1 開始的 bigint,而 id 1 是 ROOT —— 稽核紀錄的歸屬會靜默錯亂。

### 2. Model

```php
class Widget extends BaseModel {

    protected $table = 'base_widget';

}
```

### 3. 欄位宣告 —— 沒有它,CRUD 端點會 500

```php
#[Declared(WidgetDeclaration::class)]
class Widget extends BaseModel { }
```

```php
class WidgetDeclaration implements Declares {

    /**
     * @return array<string, Definition>
     */
    public function definitions(): array {
        return array_merge(
            Definitions::primaryKey(),
            [
                'title' => Definition::text(),
                'category_id' => Definition::integer()
            ],
            Definitions::schedules(),
            Definitions::auditings()
        );
    }

    public function metadata(): Metadata {
        return new Metadata('widget', 'title');
    }

}
```

`Metadata` 的第一個參數是 **alias,它必須等於選單節點的路徑前綴**;第二個是「這一列叫什麼」的欄位（麵包屑與排序頁會用）。第三個參數可以指定父層關聯,巢狀資源才需要。第四個參數 `ranking` 可指定排序用的欄位名稱,`CrudController` 會用它推導預設的 `$sorting`/`$sortable`;第五、六個參數 `enable`/`disable` 標記上下架時間欄位,接上後解鎖 `POST admin/schedule/toggle`(單筆立即切換)與 `admin/{prefix}/arrange`、`arrange/save`(拖曳式批次上下架)兩支既有 API。

沒有 `#[Declared]` 的 model 一進 CRUD 端點就是 `undeclared-model`。

**JSON 欄位形狀依情境動態變動,用 `Definition::composite()`。**

```php
'data' => Definition::composite('announcement-data')
```

參數是一個 group 名稱,對應 `config('matrix.variants.{group}')`:

```php
'variants' => [
    'announcement-data' => [
        'driver' => AnnouncementTypeResolver::class,
        'banner' => BannerFields::class,
        'text' => TextFields::class
    ]
]
```

`driver` 指向你自己實作的 `TypeResolver`(`resolve(?Model $model, mixed $input): ?string`),負責當下算出一個「type 字串」;其餘 key 是「type 字串 → 子欄位定義 class」的對照表,每個子欄位定義 class 實作 `Variant`(格式跟 `Declares::definitions()` 一樣,但不用寫 `metadata()`)。

**這裡刻意用 config 而不是 cfg。** 別的 driver(驗證碼、簡訊、翻譯、地理位置)都放 cfg,因為那些是營運要換的設定;variant 的值是**要被實例化的類別名稱**,而 cfg bundle 只要進了 `matrix.resource-cfg` 白名單就能從資源後台編輯 —— 類別接線不該是線上可改的東西。子欄位的**標題**仍然走 i18n(`model/{group}.php`),那才是該線上編輯的部分。

**前端不會知道這是一個 composite —— 引擎把它拆成一般欄位送出去。** 一個 composite 在表單端點(`new` / `get` / `insert` / `update`)會被換成**每個子欄位一個 column**,名字是 `{欄位名}__{子欄位名}`:

```
columns:  … , data__layout, data__lead, …
data:     … , data__layout: null, data__lead__tw: null, data__lead__en: null, …
```

translatable 的子欄位就是一個 `translatable: true` 的普通 column,前端照它既有的規則展成 `__{locale}`。送出時 body 也是這個扁平形狀,引擎存檔前折回巢狀 JSON —— **資料庫存的東西沒有變**,仍然是 `{"layout": "wide", "lead": {"tw": …, "en": …}}`。

因此:

- `{欄位名}__` 是**保留前綴**。真的有一個叫 `data__layout` 的實體欄位就會撞,引擎不會警告。
- 子欄位的標題查 `resources/i18n/{locale}/model/{group}.php`(不是 `model/{資料表}.php`),`{子欄位名}:placeholder` / `:remark` 同一份。**同一個 group 的所有 type 共用這一份**,所以同名的子欄位會拿到同一個標題;要讓某個 type 的子欄位有自己的說法,再放一份 `model/{group}-{type}.php`,它**逐 key 覆蓋**共用那份(跟 `model/default.php` 與 `model/{資料表}.php` 的關係一樣),沒寫到的 key 落回共用層,檔案不存在就只剩共用層。
- 子欄位可以宣告 `tab`、`group`、`required`、`rule`、`options`、`presentation`,全部照一般欄位生效。
- **清單與匯出不展開**,composite 只在表單情境有意義。

**這個欄位不需要對應到任何真實的 DB 欄位存 type 值**——`TypeResolver::resolve()` 要依據什麼判斷(另一個欄位的值、目前操作者、資料庫查詢……)完全是你的邏輯,引擎只在需要知道「這次要用哪個 variant」的當下呼叫它。**沒有設定 `driver`、或算出來的 type 沒有對應的 `Variant` 時,這個欄位一個 column 都不會出現**,送出時也不驗證、不寫入,原值保留。只支援一層,子欄位不能再是 `Definition::composite()`。

要讓 `new` 開在某個 type 上(例如讓使用者先選型別再進表單),把它當 body 送給 `new` 即可:`POST admin/block/new` 帶 `{"type": "banner"}`。引擎會依它展開子欄位,並把**已宣告且可寫**的輸入值反映回回應的 `data`,表單一開就是填好的。呼叫端亂塞的鍵不會被回吐。

**套件自己宣告了四個 group,而且全部預設不接線。** 沒有在 `config('matrix.variants')` 接上以前,那個 `data` 欄位在後台一個 column 都不會出現,`api/common/*` 則把儲存值原樣吐出來(沒存過是 `{}`)。

| group | 欄位 | `resolve()` 能依據什麼 |
|---|---|---|
| `page-data` | `base_page.data` | 頁面**沒有** `type` 欄位。通常回傳固定字串(全站共用一組欄位),或依 `path` 分流 |
| `menu-data` | `base_menu.data` | 選單**沒有** `type` 欄位。依資料判斷,例如 `parent_id` 是否為 null(頂層與子層用不同欄位) |
| `block-data` | `base_block.data` | 直接讀 `type` 欄位;`new` 時讀 body 的 `type` |
| `block-item-data` | `base_block_item.data` | 查父區塊的 `type`,但對到**另一組** `Variant`,不是父區塊那一組 |

**`resolve()` 的第二個參數只有 `new` / `insert` / `update` 拿得到值。** `get`(編輯頁)、`api/common/page`、`api/common/menu` 一律傳 `null`。所以 page 與 menu 的 driver 必須只靠 model 或常數就能決定 —— 只看 `$input` 的寫法會在編輯頁與前台端點失效。

`page-data` 的最小接線是一個回傳常數的 driver:

```php
// config/matrix.php
'variants' => [
    'page-data' => [
        'driver' => PageTypeResolver::class,
        'standard' => StandardPageFields::class
    ]
]

// app/Support/PageTypeResolver.php
class PageTypeResolver implements TypeResolver {

    public function resolve(?Model $model, mixed $input): ?string {
        return 'standard';
    }

}
```

`menu-data` 形狀一樣,差別只在 driver 讀 model:

```php
public function resolve(?Model $model, mixed $input): ?string {
    return $model?->getAttribute('parent_id') === null ? 'root' : 'child';
}
```

子欄位標題分別放 `resources/i18n/{locale}/model/page-data.php` 與 `model/menu-data.php`。

### 4. Controller

```php
class WidgetController extends CrudController {

    protected string $model = Widget::class;

    protected ?array $lists = ['title', 'category.title=category_title', 'enable_time'];

    protected array $updates = ['*title', 'category_id:select', 'enable_time', 'disable_time'];

}
```

`*` 開頭代表必填。`!` 開頭代表唯讀。`+` 開頭代表虛擬。`=` **開頭**代表鎖定(見下段)。`=` 夾在中間是別名(`alias=source`)。`.` 走關聯。`:` 後面是型別或呈現方式。

**鎖定(`=`)是「可寫但不可編輯」。** 值在表單開啟之前就決定了(例如區塊的型別由型別選擇器決定),所以它照樣驗證、照樣寫入,但 `columns[]` 上的 `writable` 是 `false`,前端據此把輸入框停用。`readonly`(`!`)是另一回事:不驗證、不寫入。鎖定同時隱含必填 —— 值既然是先決定好的,就不該是空的;要「鎖定但可為空」請用陣列語法 `['name' => 'x', 'locked' => true]`。

### 5. 路由 —— 必須掛在 `admin` 前綴之下

```php
Route::middleware(['encrypted-api', 'envelope-api', 'locale-api'])->group(function () {
    Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
        Route::middleware(['user-api', 'permission-api'])->group(function () {
            ActionRoutes::mount('widget', WidgetController::class);
        });
    });
});
```

**前綴不對,`AdminPermission` 認不出這是套件的路由,全部 403。**

**`encrypted-api` 漏掉的話,那一組路由不會解密,但前端還是會加密。** 前端是按前綴決定要不要封信封的(它不知道哪些路由掛了什麼 middleware),所以你自己的 `admin` 端點只要少掛這一層,收到的 body 就是 `{kid, epk, iv, ts, ct}` 這個信封本身,症狀是每一個欄位都 `validation-failed`。**這三個別名的順序也是約束**:`encrypted-api` 必須在 `envelope-api` 之前,否則錯誤信封不會被加密。

### 6. 選單與翻譯

`resources/menu/app.php`（然後把 `admin-menus` 改成 `'app base'`）:

```php
'catalog' => ['icon' => 'fa-solid fa-box', 'ranking' => 300, 'parent' => null],

    'widget' => ['icon' => 'fa-solid fa-cube', 'ranking' => 100, 'parent' => 'catalog', 'group' => true, 'tag' => 'query'],

        'widget/{id}' => ['parent' => 'widget', 'tag' => 'query'],
        'widget/{id}/update' => ['parent' => 'widget', 'tag' => 'update'],
        'widget/delete' => ['parent' => 'widget', 'tag' => 'delete'],
        'widget/insert' => ['parent' => 'widget', 'tag' => 'insert'],
        'widget/new' => ['parent' => 'widget', 'tag' => 'insert'],
```

`resources/i18n/en/menu/app.php` 放對應標題,`resources/i18n/en/model/base_widget.php` 放欄位標題:

```php
return [
    'title' => 'Name',
    'title:placeholder' => 'Enter a name',
    'category_id' => 'Category',
];
```

**欄位標題找不到時的行為是「看起來正常但可能不對」** —— 先退到 `model/default.php` 的通用標籤（`create_time` 之類的共用欄位都在那裡）,再退到字面 `{title}`。所以漏翻譯不一定看得出來。

---

## 前台身分（member / vendor）

套件只提供零件,**整個流程歸你**:

| 事實 | 說明 |
|---|---|
| **沒有登入端點** | 自己用 `AuthToken::issue()` + `IdentityToken::attach()` + `login-throttle-api:{bundle}` 組 |
| **你的 member / vendor 資料表必須用 `primaryKey()`** | 用 `$table->id()` 會拿到 id = 1 的第一筆 —— 而 id 1 是 ROOT,稽核歸屬會靜默錯亂 |
| 那兩張表也必須帶 `auditings()` 四個欄位 | 稽核軌跡與建立者推導都靠它們 |
| 登出的語義、驗證碼生命週期、密碼規則 | 全部由你決定。要驗證碼的話不用自己接 driver:`app(CaptchaService::class)->driver('captcha-turnstile')` 給你一個 driver,`generate()` 發、`verify()` 收。bundle 名是**必填**的——套件沒有替前台準備設定鍵,要嘛寫死,要嘛自己開一個 config 讓它可調。解不出 driver 一律 `invalid-captcha-driver`,不會靜默放行 |

---

## 訊息

### 送訊息

`MailService`、`SmsService`、`PushService`、`TelegramService` 共用同一組公開介面(定義在 `MessageService`):`schedule()`、`send()`、`resend()`、`cancel()`。

```php
app(MailService::class)->schedule(now()->addHour(), 'alice@example.com', 'welcome', ['name' => 'Alice']);
app(SmsService::class)->send('0912345678', 'otp', ['code' => '123456']);
app(PushService::class)->send('42', null, [], ['provider' => 'webpush', 'title' => '通知', 'content' => '你有一筆新訂單']);
app(TelegramService::class)->send('42', null, [], ['provider' => 'telegram', 'content' => '你有一筆新訂單']);

app(MailService::class)->resend($id);
app(MailService::class)->cancel($id);
```

`schedule($at, $to, $template = null, $vars = [], $options = [])` 回傳寫進 `base_mail_log` / `base_sms_log` / `base_push_log` / `base_telegram_log` 的那一列;`send($to, $template = null, $vars = [], $options = [])` 是 `schedule(now(), ...)` 的薄封裝,等同「立即送」。樣板檔案放 `resources/i18n/{語系}/template/{name}.php`。`resend(int|string $reference)` 複製一筆既有紀錄(清空 `send_time` / `response` / `error`、`schedule_time` 設回 `now()`)重新排入佇列,原始那筆不受影響;`cancel(int|string $reference)` 只對還是 `Scheduled` 的紀錄生效,把它改成 `Failed`(`error` 設為 `'cancelled'`),對已經是終端狀態(`Success` / `Failed`)的紀錄呼叫是冪等的,不會報錯也不會改動它。

| 事實 | 說明 |
|---|---|
| 回傳的列是 `Scheduled`,不是已送出 | 真正的送出在 worker。`schedule()` 只負責寫列 + 通知有東西要送 |
| `$at` 到期了才派工 | 未來時間只寫列,等 `messages:dispatch` 那一輪撈到 |
| **樣板可以是 `null`,provider 不行** | 樣板要嘛自己指名 `provider`,要嘛 `$options` 給。都沒有就 `invalid-message-provider`;供應商沒設 `driver` 就 `message-provider-has-no-driver` |
| `$options` 覆蓋樣板的渲染結果 | 只認 `provider`、`subject`、`title`、`content`、`data` 五個 key。值是 `null` **不算**覆蓋。`title` 只有 push 用;`data` 給 push(額外 payload)與 telegram(`parse_mode` 等 Bot API 選項)用;mail / sms 不讀這兩個 key。telegram 的 `data` **蓋不掉 `chat_id` 與 `text`**——收件目標由訂閱解析與 sandbox 決定,內容一律是記錄下來的 `content`,否則 `base_telegram_log` 會對不上實際送出的東西,sandbox 也會被繞過 |
| mail 的寄件者是當下快照 | `sender` 寫入時從 `cfg('{provider}.from-address')` 取,之後改設定不影響已排程的訊息 |
| `resend()` 的 `ip` 是重發當下的 IP | 複製出來的新紀錄一樣會經過 `creating` 的 `ip` generator,存的是操作 `resend` 的人的 IP,不是原始訊息建立時的 IP |

### 派送與 worker

| 事實 | 說明 |
|---|---|
| **一個發送工作只送一封,節奏由 worker 的序列性決定** | 工作的單位是 channel。它撈這個 channel 最前面一筆（`schedule_time` 再 `id`）、送出、睡掉該供應商的 `interval`、然後派下一個工作接手;撈不到就結束,不派後繼。sleep 佔住的是那條 queue 唯一的 worker,所以兩次送出之間一定隔了 `interval`,不管佇列裡有幾個工作 —— 套件不記錄「上次幾點送的」,沒有任何節流狀態。`messages:dispatch` 只是鏈條斷掉時的安全網（`EXISTS` 檢查,有東西在等就派一個工作） |
| **每條 queue 最多一個 worker** | 節流靠的是 worker 的序列性,所以多開一個 worker 就是速率翻倍。內建的四個 channel 各有自己的 queue(`messaging-mail`／`messaging-sms`／`messaging-push`／`messaging-telegram`),要開四個 worker;**漏開一條就是那條 channel 全部停在 `Scheduled`**,而 `messages:dispatch` 每分鐘照樣回成功。套件不偵測 worker 存活 —— 那歸行程監管（systemd／supervisor／Horizon）,你為了跑 `queue:work` 本來就需要它們 |
| **`interval` < `--timeout` < `retry_after`** | 套件無法幫你算這三個數字:工作在派送時只知道 channel,還不知道會送到哪個供應商。`--timeout`（預設 60）要大於「最大的 `interval` + 單筆送出耗時」,connection 的 `retry_after` 要再大於它 |
| **同一個 channel 嚴格 FIFO** | 送出順序就是 `schedule_time` 的順序,不分供應商。所以一個供應商的 `interval` 也會延後排在它後面的其他供應商的訊息 |
| **隊頭送不出去會卡住整個 channel** | 排程時 `schedule()` 就會擋掉沒有 driver 的供應商,所以這只發生在「排程之後設定才壞掉」:`driver` 被拿掉、改成不是 driver 的類別、或 cfg bundle 消失。工作會失敗（進 `failed_jobs`）、不動任何記錄,而那一筆還在隊頭 —— 後面的訊息（包含其他供應商的）都送不出去。排程每分鐘再派一次,所以壞多久就累積多少筆 `failed_jobs`。要隔離就給那個供應商自己的 channel（也就是自己的 queue 與 worker） |
| **設定壞掉的失敗只在 `failed_jobs` 看得到** | `ServiceException::report()` 回 `false`,所以那種例外不進 Laravel 的 exception handler,Sentry 那類收不到 |
| **送出失敗會寫一筆 `Log::error`** | 這裡說的是 driver 真的被呼叫之後才失敗:記錄標成 `Failed`,鏈條照樣往下走。事件名 `messaging.{channel}.failed`,context 帶 channel、provider、訊息 id 與寫進 `error` 欄位的內容。`error` 可能含供應商回應（`response` 欄位本來就存完整回應）,log 的保存政策要照這個前提設 |
| **worker 硬掉在送出中間會重送** | 送出成功但寫回失敗、或行程被 SIGKILL／OOM 砍在 driver 呼叫途中時,那一列還是 `Scheduled`,下一個工作會再送一次。優雅重啟（SIGTERM）不受影響,Laravel 會讓當前工作跑完。套件選的是「寧可重送也不漏送」,而且**沒有重試次數上限** |
| queue connection 設成 `sync` | 送出會變成同步執行,`interval` 照樣生效（在同一個行程裡睡）,但鏈條會變成遞迴呼叫 |

### 後台查詢、重發與取消

`mail-log` / `sms-log` / `push-log` / `telegram-log` 四個 Admin controller 都繼承同一個抽象基底 `MessageLogController`,唯讀:只有列表(`{prefix}`)、查看單筆(`{prefix}/{id}`)、`{prefix}/{id}/resend`、`{prefix}/{id}/cancel` 四個動作有登記選單節點、對授權使用者開放,其餘 CRUD 動作(見上方[端點](#端點)表的附註)一律 403。`resend`/`cancel` 內部就是呼叫對應 `MessageService` 的同名方法,回傳 `{"id": ...}`(`resend` 是新那筆的 id)。四個 controller 本身沒有額外邏輯,只設定各自的 `$model`、`$service`、`$lists`。

### Push 訂閱(Member)

Push 是唯一需要前端配合的通道:瀏覽器要先用 Web Push API 取得 `endpoint` / `keys.p256dh` / `keys.auth`,再呼叫 `MemberPushSubscriptionController` 存進 `base_push_subscription`,`PushService::send()` 才推得出去。

| 端點 | 說明 |
|---|---|
| `api/member/push/subscribe` | 帶 `endpoint`、`keys.p256dh`、`keys.auth`。以 `endpoint` 為準:同一個 `endpoint` 再訂閱一次是更新既有紀錄,不會重複 |
| `api/member/push/unsubscribe` | 帶 `endpoint`。只刪目前這個會員自己名下、`endpoint` 相符的那一筆,刪不到不報錯 |

Service worker 註冊、`Notification.requestPermission()`、呼叫這兩支 API,都是前端(消費端 repo)的工作,不在這個套件範圍內。

### Telegram 綁定與 Webhook

Telegram 的訂閱對象是**後台使用者(`User`),不是前台會員(`Member`)**——這個通道設計給營運/維運告警之類「後台人員自己訂閱通知」的情境。`base_telegram_log.chat_id` 是**雙語意欄位**,不額外加欄位區分,靠「查不查得到訂閱紀錄」解析:`TelegramDriver` 先判斷這個值是不是負數——Telegram 的規則是群組/頻道 chat_id 恆為負數、個人(這裡是 `User`)恆為正數,天然不會撞號,所以負數直接當成目標 chat_id 送(**固定群組/頻道**模式,`send($chatId, ...)` 時 `$to` 直接傳 chat_id 字串),連查都不用查;非負數才去查 `base_telegram_subscription.user_id`,查到就送去對應的訂閱 `chat_id`(**User 訂閱**模式),查不到就跟負數的情況一樣、把值本身當字面 chat_id 送。

跟 Push 用 JS 直接呼叫 subscribe API 不同,User 訂閱需要使用者主動在 Telegram 裡點一個連結、跟 bot 對話。綁定端點掛在 `admin` 前綴、`user-api` middleware 底下(跟 `admin/user/preference` 同層,不需要額外權限,登入即可管理自己的綁定):

| 端點 | 說明 |
|---|---|
| `admin/user/telegram/link` | 產生一次性 token(10 分鐘內有效,存在 cache,不進資料表),回傳 `https://t.me/{bot-username}?start={token}` 給前端導頁或顯示 QR code |
| `admin/user/telegram/unsubscribe` | 刪掉目前這個使用者自己名下的 `base_telegram_subscription` |
| `api/telegram/webhook` | Telegram 伺服器呼叫,**不是**給前端用的。收到 `/start {token}` 就把 token 換回 user id、寫入(或更新)那個使用者的 `chat_id` |

這是套件目前唯一一個**接收外部主動呼叫**的端點(前面所有端點都是「我們主動對外呼叫」或「被已登入使用者呼叫」),所以認證方式也不同——不是 token,是 header:呼叫方要帶 `X-Telegram-Bot-Api-Secret-Token`,值要等於 `cfg('telegram.webhook-secret')`,對不上或這把密鑰根本沒設定,一律 403。跑 `php artisan messages:telegram-webhook` 之前記得先把 `telegram.bot-token` 與 `telegram.webhook-secret` 設定好(這支指令本身也會檢查,兩個空字串會直接失敗、不呼叫 Telegram)。

無效或已過期的 token 會被靜靜忽略(不是 `/start` 開頭的訊息也一樣),webhook 一律回 200 系列的成功——Telegram 只在乎「有沒有收到」,不在乎綁定有沒有真的成功。

### 自訂訊息通道

| 事實 | 說明 |
|---|---|
| 註冊點是 `config/matrix.php` 的 `messaging.channels`,**但那是巢狀 key** | 宣告 `messaging` 會整個取代掉內建的 mail / sms / push / telegram |
| **每個 channel 都必須宣告 `queue`** | 沒宣告就是 `invalid-message-channel`,那個 channel 完全不能用。加一個 channel 就是加一條 queue 加一個 worker |
| 每個供應商還要一份 `resources/cfg/{provider}.php` | **`driver` 是必填** —— 沒有它 `schedule()` 當場回 `message-provider-has-no-driver`。其餘的鍵照 [cfg 設定鍵](#cfg-設定鍵)裡 `gmail`、`mitake`、`webpush`、`telegram` 四組的形狀寫 |
| **供應商與樣板名稱要跨 channel 唯一** | —— |
| 樣板必須指名 `provider` | 否則 `schedule()` 當場回 `invalid-message-provider` |

---

## 參考

以下表格是**契約**。守門測試（`tests/Feature/ReadmeTest.php`）會把第一欄的識別字拿去跟實際的路由表、設定、指令、資料表對照 —— 表格寫錯或程式改了沒回來改表,測試就會紅。

### 端點

除了 `GET api/files/{path}`(公開檔案讀取,見下方說明)之外,其餘全部是 `POST`。路徑省略前綴（`admin`、`api` 與 `vendor` 分別來自 `matrix.admin-api-prefix`、`matrix.api-prefix` 與 `matrix.vendor-api-prefix`）。

`POST encryption-key` 是唯一**不在任何前綴底下**的端點:三組 API 共用同一個金鑰圈,而它是拿公鑰的地方,所以它不能落在任何一組的加密範圍裡——沒有公鑰就沒有信封,拿公鑰的請求自己不可能是信封。它同時也宣告了 `#[Action(encrypted: false)]`,所以「它是明文」是寫下來的事實,不是靠它剛好落在所有前綴之外推論出來的。

`GET api/files/{path}` 是唯一的例外:不需要身分、不走信封,直接回 302(公開上傳檔案)、200(串流,drive-linked 檔案)或 404(私有/不存在)。路由用 `Route::get()` 註冊,Laravel 會自動一併掛上 `HEAD`(任何 `Route` 只要方法含 `GET` 就一定含 `HEAD`,框架內建行為,無法關閉),所以「端點」表裡這一列的方法欄是 `GET\|HEAD`。

| 方法 | 路徑 | 需要 |
|---|---|---|
| POST | `admin/auth/captcha` | 匿名 |
| POST | `admin/auth/login` | 匿名（有節流） |
| POST | `admin/auth/logout` | 登入 |
| POST | `admin/auth/mfa` | 匿名（有節流） |
| POST | `admin/auth/mfa/confirm` | 登入 |
| POST | `admin/auth/mfa/disable` | 登入 |
| POST | `admin/auth/mfa/setup` | 登入 |
| POST | `admin/auth/passkey/options` | 匿名（有節流） |
| POST | `admin/auth/passkey/login` | 匿名（有節流） |
| POST | `admin/auth/passwd` | 登入 |
| POST | `admin/auth/profile` | 登入 |
| POST | `admin/i18n/get` | **匿名** |
| POST | `admin/file/upload` | 登入 |
| POST | `admin/file/update` | 登入 |
| POST | `admin/drive/root` | 登入 |
| POST | `admin/drive/home` | 登入 |
| POST | `admin/drive/group` | 登入 |
| POST | `admin/drive/{id}` | 登入 |
| POST | `admin/drive/{id}/children` | 登入 |
| POST | `admin/drive/{id}/folder` | 登入 |
| POST | `admin/drive/{id}/upload` | 登入 |
| GET\|HEAD | `admin/drive/{id}/download` | 登入 |
| POST | `admin/drive/{id}/download` | 登入 |
| POST | `admin/drive/{id}/publish` | 登入 |
| POST | `admin/drive/{id}/rename` | 登入 |
| POST | `admin/drive/{id}/move` | 登入 |
| POST | `admin/drive/{id}/path` | 登入 |
| POST | `admin/drive/{id}/delete` | 登入 |
| POST | `admin/drive/trashed` | 登入 |
| POST | `admin/drive/{id}/restore` | 登入 |
| POST | `admin/manipulation-log/query` | 登入 |
| POST | `admin/schedule/toggle` | 登入 |
| POST | `admin/city` | 授權 |
| POST | `admin/city/new` | 授權 |
| POST | `admin/city/insert` | 授權 |
| POST | `admin/city/{id}` | 授權 |
| POST | `admin/city/{id}/update` | 授權 |
| POST | `admin/city/delete` | 授權 |
| POST | `admin/city/sort` | 授權 |
| POST | `admin/city/sort/save` | 授權 |
| POST | `admin/city/{city_id}/area` | 授權 |
| POST | `admin/city/{city_id}/area/new` | 授權 |
| POST | `admin/city/{city_id}/area/insert` | 授權 |
| POST | `admin/city/{city_id}/area/{id}` | 授權 |
| POST | `admin/city/{city_id}/area/{id}/update` | 授權 |
| POST | `admin/city/{city_id}/area/delete` | 授權 |
| POST | `admin/city/{city_id}/area/sort` | 授權 |
| POST | `admin/city/{city_id}/area/sort/save` | 授權 |
| POST | `admin/menu` | 授權 |
| POST | `admin/menu/new` | 授權 |
| POST | `admin/menu/insert` | 授權 |
| POST | `admin/menu/{id}` | 授權 |
| POST | `admin/menu/{id}/update` | 授權 |
| POST | `admin/menu/delete` | 授權 |
| POST | `admin/menu/arrange` | 授權 |
| POST | `admin/menu/arrange/save` | 授權 |
| POST | `admin/menu/{parent_id}/children` | 授權 |
| POST | `admin/menu/{parent_id}/children/new` | 授權 |
| POST | `admin/menu/{parent_id}/children/insert` | 授權 |
| POST | `admin/menu/{parent_id}/children/{id}` | 授權 |
| POST | `admin/menu/{parent_id}/children/{id}/update` | 授權 |
| POST | `admin/menu/{parent_id}/children/delete` | 授權 |
| POST | `admin/menu/{parent_id}/children/arrange` | 授權 |
| POST | `admin/menu/{parent_id}/children/arrange/save` | 授權 |
| POST | `admin/page` | 授權 |
| POST | `admin/page/new` | 授權 |
| POST | `admin/page/insert` | 授權 |
| POST | `admin/page/{id}` | 授權 |
| POST | `admin/page/{id}/update` | 授權 |
| POST | `admin/page/delete` | 授權 |
| POST | `admin/page/arrange` | 授權 |
| POST | `admin/page/arrange/save` | 授權 |
| POST | `admin/page/{page_id}/block` | 授權 |
| POST | `admin/page/{page_id}/block/new` | 授權 |
| POST | `admin/page/{page_id}/block/insert` | 授權 |
| POST | `admin/page/{page_id}/block/{id}` | 授權 |
| POST | `admin/page/{page_id}/block/{id}/update` | 授權 |
| POST | `admin/page/{page_id}/block/delete` | 授權 |
| POST | `admin/page/{page_id}/block/arrange` | 授權 |
| POST | `admin/page/{page_id}/block/arrange/save` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item/new` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item/insert` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item/{id}` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item/{id}/update` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item/delete` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item/arrange` | 授權 |
| POST | `admin/page/{page_id}/block/{block_id}/item/arrange/save` | 授權 |
| POST | `admin/geolocation` | 授權 |
| POST | `admin/user` | 授權 |
| POST | `admin/user/new` | 授權 |
| POST | `admin/user/insert` | 授權 |
| POST | `admin/user/{id}` | 授權 |
| POST | `admin/user/{id}/update` | 授權 |
| POST | `admin/user/{id}/disable-mfa` | 授權 |
| POST | `admin/user/{id}/revoke-passkeys` | 授權 |
| POST | `admin/user/delete` | 授權 |
| POST | `admin/user/preference/get` | 登入 |
| POST | `admin/user/preference/save` | 登入 |
| POST | `admin/user/telegram/link` | 登入 |
| POST | `admin/user/telegram/unsubscribe` | 登入 |
| POST | `admin/user/passkey` | 登入 |
| POST | `admin/user/passkey/register/options` | 登入 |
| POST | `admin/user/passkey/register` | 登入 |
| POST | `admin/user/passkey/{id}/rename` | 登入 |
| POST | `admin/user/passkey/{id}/delete` | 登入 |
| POST | `admin/group` | 授權 |
| POST | `admin/group/new` | 授權 |
| POST | `admin/group/insert` | 授權 |
| POST | `admin/group/{id}` | 授權 |
| POST | `admin/group/{id}/update` | 授權 |
| POST | `admin/group/delete` | 授權 |
| POST | `admin/resource/cfg` | 授權 |
| POST | `admin/resource/cfg/{id}` | 授權 |
| POST | `admin/resource/cfg/{id}/update` | 授權 |
| POST | `admin/resource/i18n` | 授權 |
| POST | `admin/resource/i18n/{id}` | 授權 |
| POST | `admin/resource/i18n/{id}/update` | 授權 |
| POST | `admin/resource/i18n/menu` | 授權 |
| POST | `admin/resource/i18n/menu/{id}` | 授權 |
| POST | `admin/resource/i18n/menu/{id}/update` | 授權 |
| POST | `admin/resource/i18n/model` | 授權 |
| POST | `admin/resource/i18n/model/{id}` | 授權 |
| POST | `admin/resource/i18n/model/{id}/update` | 授權 |
| POST | `admin/resource/i18n/options` | 授權 |
| POST | `admin/resource/i18n/options/{id}` | 授權 |
| POST | `admin/resource/i18n/options/{id}/update` | 授權 |
| POST | `admin/resource/i18n/template` | 授權 |
| POST | `admin/resource/i18n/template/{id}` | 授權 |
| POST | `admin/resource/i18n/template/{id}/update` | 授權 |
| POST | `admin/translation` | 授權 |
| POST | `admin/mail-log` | 授權 |
| POST | `admin/mail-log/{id}` | 授權 |
| POST | `admin/mail-log/{id}/resend` | 授權 |
| POST | `admin/mail-log/{id}/cancel` | 授權 |
| POST | `admin/sms-log` | 授權 |
| POST | `admin/sms-log/{id}` | 授權 |
| POST | `admin/sms-log/{id}/resend` | 授權 |
| POST | `admin/sms-log/{id}/cancel` | 授權 |
| POST | `admin/push-log` | 授權 |
| POST | `admin/push-log/{id}` | 授權 |
| POST | `admin/push-log/{id}/resend` | 授權 |
| POST | `admin/push-log/{id}/cancel` | 授權 |
| POST | `admin/telegram-log` | 授權 |
| POST | `admin/telegram-log/{id}` | 授權 |
| POST | `admin/telegram-log/{id}/resend` | 授權 |
| POST | `admin/telegram-log/{id}/cancel` | 授權 |
| POST | `api/common/city` | 匿名 |
| POST | `api/common/menu` | 匿名 |
| POST | `api/common/page` | 匿名 |
| GET\|HEAD | `api/files/{path}` | **匿名,不走信封** |
| POST | `api/member/preference/get` | 登入 |
| POST | `api/member/preference/save` | 登入 |
| POST | `api/member/push/subscribe` | 登入 |
| POST | `api/member/push/unsubscribe` | 登入 |
| POST | `api/telegram/webhook` | 匿名* |
| POST | `encryption-key` | 匿名 |
| POST | `vendor/preference/get` | 登入 |
| POST | `vendor/preference/save` | 登入 |

「授權」= 登入 + 該選單節點的權限。`user` / `group` 的 `export`、`copy`、`sort` 端點存在但**套件出貨的選單沒有對應節點**,所以預設對所有人 403 —— 那三個動作在套件自己的兩個 controller 上是關閉的。`mail-log` / `sms-log` / `push-log` / `telegram-log` 更進一步:選單只登記了列表、`{id}`、`{id}/resend`、`{id}/cancel` 四個節點,其餘十個(`new`、`insert`、`{id}/update`、`{id}/copy`、`delete`、`export`、`sort`、`sort/save`、`arrange`、`arrange/save`)全部因為選單沒有節點而預設對所有人(含 ROOT)403——是刻意設計,訊息紀錄只能查詢與重發/取消,不能被手動增刪改。`api/telegram/webhook` 標「匿名*」是因為它不掛任何登入態 middleware(呼叫方是 Telegram 伺服器,沒有我們的 session/token 可帶),但自己驗證 `X-Telegram-Bot-Api-Secret-Token` header 等於 `cfg('telegram.webhook-secret')`,**沒設定這把密鑰就對所有請求一律 403**(見[Telegram 綁定與 Webhook](#telegram-綁定與-webhook))。

### 設定鍵

`config/matrix.php`。改任何一個都要記得 `mergeConfigFrom` 只合併頂層 key。

| 鍵 | 出貨值 | 說明 |
|---|---|---|
| `matrix.admin-api-encryption` | `true` | `admin` 前綴要不要傳輸加密。三組前綴各有各的開關,互不影響。**這是明確開關,伺服器不會自動偵測環境** |
| `matrix.admin-api-prefix` | `'admin'` | 後台路由前綴 |
| `matrix.admin-captcha-provider` | `'captcha-none'` | 後台登入要用哪個驗證碼 driver,對應 `resources/cfg/{值}.php`。出貨四個:**`captcha-none`(預設,不要驗證碼——`captcha` 回 `data: null`、`login` 不再要求 token/code)**、`captcha-image`(圖形)、`captcha-turnstile`(Cloudflare Turnstile)、`captcha-recaptcha`(Google reCAPTCHA)。**預設是關的,因為前端的 `config.captcha` 也預設不畫任何驗證碼**——兩邊不成對的話登入一律 422(driver 宣告自己要的憑證欄位,所以 `fields` 會指名缺的是 `token` 還是 `code`),所以要驗證碼就兩邊一起開。解不出 driver 一律 `invalid-captcha-driver`,不會靜默放行。這個鍵只管後台;前台要驗證碼是你自己呼叫 `CaptchaService`,見[前台身分](#前台身分member--vendor) |
| `matrix.admin-menus` | `'base'` | 要載入哪些選單 bundle,空白分隔,排前面的覆蓋排後面的 |
| `matrix.api-encryption` | `false` | `api` 前綴要不要傳輸加密。出貨關著,因為這一組的呼叫方包含 Telegram 這種不可能配合的第三方 |
| `matrix.api-prefix` | `'api'` | 前台路由前綴 |
| `matrix.date-format` | `'Y-m-d'` | 日期顯示格式 |
| `matrix.datetime-format` | `'Y-m-d H:i:s'` | 日期時間顯示格式 |
| `matrix.drive-disk` | `'local'` | 雲端硬碟實體檔案的 disk,獨立於 `file-*-disk`。**必須是 local driver**(見下方限制) |
| `matrix.file-private-disk` | `'local'` | 非公開檔案的 disk。**必須是 local driver** |
| `matrix.file-public-disk` | `'public'` | 公開檔案的 disk。**必須是 local driver** |
| `matrix.geolocation-provider` | `'ip2location-bin'` | IP 地理位置查詢要用哪個 driver,對應 `resources/cfg/{值}.php` |
| `matrix.locales` | `'tw en'` | 允許的語系,對應 `resources/i18n/{語系}/` |
| `matrix.member-model` | `Member::class` | 會員 model,宿主可換成自己的 |
| `matrix.messaging` | 見範本 | channel 註冊。每個 channel 都要有 `model` 與 `queue`。**巢狀 key,宣告就整份取代** |
| `matrix.packages` | `'app base'` | 資源疊層順序 |
| `matrix.passkey-rp-id` | `null` | Passkey Relying Party ID。`null` 時 fallback 用當次請求的主機名稱——若後台前端與此 API 不同源,務必明確設定,見[已知限制與取捨](#已知限制與取捨)。允許的 origin 由此值(加上 `admin.passkey-allow-subdomains`)推導,預設一律要求 `https://{rp-id}`;此值出現在 `admin.passkey-http-rp-ids` 白名單裡才會額外放行 `http://{rp-id}`(本機開發用) |
| `matrix.resource-cache-enabled` | `false` | 是否快取資源 bundle defaults(檔案)與 override(DB)。預設不快取,每次都直接讀檔案/DB;設 `true` 才會讀寫 cache store |
| `matrix.resource-cache-store` | `null` | 資源 bundle defaults(檔案)與 override(DB)要快取到哪個 cache store,`null` = 用預設 store(`cache.default`)。清快取見 `matrix:clear-resource-cache` |
| `matrix.resource-cfg` | `[]` | 資源後台開放編輯的 cfg bundle 白名單,**空 = 全部不開放** |
| `matrix.resource-i18n` | `[]` | 同上,一般翻譯 |
| `matrix.resource-i18n-menu` | `[]` | 同上,選單標題 |
| `matrix.resource-i18n-model` | `[]` | 同上,欄位標題 |
| `matrix.resource-i18n-options` | `[]` | 同上,下拉選項 |
| `matrix.resource-i18n-template` | `[]` | 同上,訊息樣板 |
| `matrix.thumbnail-quality` | `80` | 縮圖 webp 編碼品質(0-100) |
| `matrix.thumbnail-sizes` | `['icon' => 64, 'thumb' => 256]` | 可用的縮圖 `size` 參數與對應寬度(px),`?size=` 帶不在此清單內的值一律回原始檔案 |
| `matrix.translation-provider` | `'google-translate'` | 內容翻譯要用哪個 driver,對應 `resources/cfg/{值}.php` |
| `matrix.variants` | `[]` | `Definition::composite()` 的接線:`{group} => ['driver' => TypeResolver 類別, {type} => Variant 類別, …]`。**刻意放 config 不放 cfg** —— 它指的是要實例化的類別,而 cfg bundle 一旦進了 `matrix.resource-cfg` 白名單就能從資源後台改 |
| `matrix.vendor-api-encryption` | `true` | `vendor` 前綴要不要傳輸加密 |
| `matrix.vendor-api-prefix` | `'vendor'` | 廠商路由前綴 |
| `matrix.vendor-model` | `Vendor::class` | 廠商 model |

白名單只擋非 ROOT。ROOT 不受它限制。

### cfg 設定鍵

可以在資源後台線上編輯,也可以在自己的 `resources/cfg/{bundle}.php` 覆蓋。

| 鍵 | 出貨值 | 說明 |
|---|---|---|
| `admin.captcha-ttl` | `300` | 驗證碼有效秒數 |
| `admin.login-throttle-max` | `5` | 每個窗口容許的登入失敗次數 |
| `admin.login-throttle-window` | `1` | 節流窗口(分鐘) |
| `admin.mfa-challenge-ttl` | `300` | MFA challenge 有效秒數 |
| `admin.mfa-trust-days` | `30` | 「信任此瀏覽器」cookie 的有效天數 |
| `admin.mfa-window` | `1` | TOTP 驗證時間漂移容忍度(±N 個 30 秒區間) |
| `admin.passkey-allow-subdomains` | `false` | 是否允許子網域的 origin 通過驗證 |
| `admin.passkey-challenge-ttl` | `120` | Passkey 註冊/登入 challenge 有效秒數 |
| `admin.passkey-http-rp-ids` | `''` | 逗號分隔的 RP ID 清單,清單內的值額外放行 `http://` origin(本機開發用,本機以外不要設)。空字串 = 一律要求 `https://`。**標記為 `readonly`,資源後台改不動**——放寬 origin 是降低認證強度的操作,只能改檔案(`resources/cfg/admin.php`) |
| `admin.passkey-timeout` | `60000` | 前端 ceremony 逾時毫秒數(供前端顯示,伺服器不強制) |
| `admin.password-pattern` | `'/^(?=.*\d)(?=.*[a-zA-Z]).{8,}$/'` | 自助改密碼、`matrix:passwd` 與使用者表單共用的密碼規則 |
| `admin.token-idle-minutes` | `30` | 後台 token 閒置多久失效 |
| `captcha-image.driver` | `ImageDriver::class` | 圖形驗證碼。答案存 cache,所以需要 `ext-gd`(含 FreeType)與跨請求共用的 cache store |
| `captcha-none.driver` | `NoneDriver::class` | 不要驗證碼。`generate()` 回 `null`、`verify()` 一律通過 |
| `captcha-recaptcha.driver` | `RecaptchaDriver::class` | Google reCAPTCHA |
| `captcha-recaptcha.action` | `'login'` | 要比對的 `action`,與回應對不上就算失敗 |
| `captcha-recaptcha.endpoint` | `'https://www.google.com/recaptcha/api/siteverify'` | 驗證端點 |
| `captcha-recaptcha.hostnames` | `''` | 允許的 token 來源網域清單(空白/逗號/分號分隔),對應 siteverify 回應的 `hostname`。**空字串 = 不檢查**,見[已知限制與取捨](#安全) |
| `captcha-recaptcha.secret` | `''` | 後端密鑰,**出貨是空的,自己填**。空字串不會放行——驗證一律失敗,登入擋下 |
| `captcha-recaptcha.threshold` | `0.5` | 分數門檻,低於就擋。回應沒有 `score` 時當成 `0.0`(擋下) |
| `captcha-turnstile.driver` | `TurnstileDriver::class` | Cloudflare Turnstile |
| `captcha-turnstile.action` | `'login'` | 同 `captcha-recaptcha.action` |
| `captcha-turnstile.endpoint` | `'https://challenges.cloudflare.com/turnstile/v0/siteverify'` | 驗證端點 |
| `captcha-turnstile.hostnames` | `''` | 同 `captcha-recaptcha.hostnames` |
| `captcha-turnstile.secret` | `''` | 同 `captcha-recaptcha.secret` |
| `encryption.grace-period` | `86400` | 輪替後舊金鑰還能解密多久(秒);`matrix:rotate-encryption-key --grace` 可以單次覆蓋 |
| `encryption.window` | `300` | 加密信封的 `ts` 容許誤差(秒),同時是同一個 `epk` 的去重保留時間(2 倍) |
| `member.login-throttle-max` | `5` | 同上,前台會員 |
| `member.login-throttle-window` | `1` | 同上,前台會員 |
| `member.password-pattern` | `'/^(?=.*\d)(?=.*[a-zA-Z]).{8,}$/'` | 同上,前台會員 |
| `member.token-idle-minutes` | `30` | 同上,前台會員 |
| `vendor.login-throttle-max` | `5` | 同上,廠商 |
| `vendor.login-throttle-window` | `1` | 同上,廠商 |
| `vendor.password-pattern` | `'/^(?=.*\d)(?=.*[a-zA-Z]).{8,}$/'` | 同上,廠商 |
| `vendor.token-idle-minutes` | `30` | 同上,廠商 |
| `gmail.driver` | `MailerMailDriver::class` | 這個供應商用哪個 driver 送,**必填** |
| `gmail.host` | `'smtp.gmail.com'` | SMTP 主機 |
| `gmail.port` | `587` | SMTP 連接埠 |
| `gmail.encryption` | `'tls'` | `ssl` 走 `smtps`,其餘走 `smtp` |
| `gmail.username` | `''` | SMTP 帳號 |
| `gmail.password` | `''` | SMTP 密碼 |
| `gmail.from-address` | `''` | 寄件者位址,寫入 `base_mail_log.sender` 時快照 |
| `gmail.from-name` | `''` | 寄件者顯示名稱 |
| `gmail.interval` | `1` | 同一個供應商兩次送出之間的最短秒數,worker 用 sleep 實現 |
| `gmail.sandbox` | `false` | 開啟後所有訊息改寄到 `sandbox-recipient` |
| `gmail.sandbox-recipient` | `''` | 沙箱收件者。`sandbox` 開著而這裡是空的就 `invalid-message-receiver` |
| `mitake.driver` | `MitakeSmsDriver::class` | 同 `gmail.driver` |
| `mitake.endpoint` | `'https://smsapi.mitake.com.tw/'` | API 端點,空字串就 `invalid-message-provider` |
| `mitake.username` | `''` | API 帳號 |
| `mitake.password` | `''` | API 密碼 |
| `mitake.accepted-status` | `'0 1 2 4'` | 視為送出成功的 `statuscode`,空白分隔。不在名單內就 `message-refused-by-provider` |
| `mitake.interval` | `1` | 同 `gmail.interval` |
| `mitake.sandbox` | `false` | 同 `gmail.sandbox` |
| `mitake.sandbox-recipient` | `''` | 同 `gmail.sandbox-recipient` |
| `webpush.driver` | `WebPushDriver::class` | 同 `gmail.driver` |
| `webpush.subject` | `''` | VAPID subject,`mailto:` 或網址 |
| `webpush.public-key` | `''` | VAPID 公鑰 |
| `webpush.private-key` | `''` | VAPID 私鑰 |
| `webpush.interval` | `1` | 同 `gmail.interval` |
| `telegram.driver` | `TelegramDriver::class` | 同 `gmail.driver` |
| `telegram.bot-token` | `''` | Bot API token,呼叫 `sendMessage`/`setWebhook` 都用它 |
| `telegram.bot-username` | `''` | 組 `link()` 回傳的 deep-link(`https://t.me/{bot-username}?start=...`)用,不含 `@` |
| `telegram.webhook-secret` | `''` | `TelegramWebhookController` 拿來比對 `X-Telegram-Bot-Api-Secret-Token` header,空字串會讓 webhook 對所有請求一律 403 |
| `telegram.interval` | `1` | 同 `gmail.interval` |
| `telegram.sandbox` | `false` | 同 `gmail.sandbox`,開啟後訊息一律送到 `sandbox-recipient` 那個 chat_id |
| `telegram.sandbox-recipient` | `''` | 同 `gmail.sandbox-recipient` |
| `file.max-size` | `0` | 上傳大小上限,**0 = 不限制** |
| `file.mime-patterns` | `''` | 型別白名單(正則,空白 = 不檢查) |
| `drive.deduplicate` | `true` | 開啟後,雲端硬碟上傳內容雜湊相同的檔案會共用同一份實體檔案,不重複寫入 |
| `drive.trash-default-days` | `30` | `drive/trashed` 預設只列出這幾天內的垃圾桶項目,可用 `days`/`all` 參數放寬,不影響 `restore` |

`gmail`、`mitake`、`webpush` 與 `telegram` 是出貨的供應商 bundle。加一個供應商就是加一份 `resources/cfg/{名稱}.php`,鍵的形狀照上面四組。

### 主控台指令

| 指令 | 作用 |
|---|---|
| `matrix:clear-resource-cache` | 清掉所有已快取的 cfg/i18n/menu/style 資源 bundle defaults 與 DB override 快取(`matrix.resource-cache-store`)。部署後改了資源檔案卻沒生效,先跑這個 |
| `matrix:make-crud` | 傳入資料表名稱(`{table}`),從已存在的資料表反向產生 Model、Declaration、Controller 草稿與 `resources/i18n/{locale}/model/{table}.php`(欄位清單、型別、`unique` 約束、translatable 欄位皆由 `information_schema` 內省;`required` 不從 schema 推斷)。不存在才寫入,`--force` 才覆寫既有檔案,`--dry-run` 只印出全部內容不寫入;路由(`routes/*.php`)、選單(`resources/menu/*.php`)與選單標題(`resources/i18n/{locale}/menu/*.php`)只印出建議片段供人工貼上,不會自動改寫這幾份共用陣列檔。**只有 `--parent` 指定的那個關聯會被轉成 `belongsTo()` 並在清單頁享有 `joined()` 智慧轉換,其餘一般外鍵欄位清單頁仍顯示裸 ID**;複合(多欄位)`unique`/`FOREIGN KEY` 約束不自動處理;只支援 `public` schema 的 PostgreSQL。**欄位若在資料庫設有 `COMMENT`,該註解會直接作為應用程式預設語系(`app()->getLocale()`)的欄位標題,其餘語系透過已設定的翻譯 provider 自動翻譯**;沒有 `COMMENT`、翻譯 provider 未設定或翻譯失敗時,該語系退回 `TODO: {欄位}` 並列入警示清單 |
| `matrix:passwd` | 設定後台帳號密碼,建立管理員的唯一官方入口 |
| `matrix:prune-drive-files` | 刪掉不再被任何 CRUD 記錄引用的 drive-linked `base_file`,每次執行都是即時掃描全部資料、當場判斷、當場刪除,沒有寬限期。**只掃描寫進 model `#[Declared]` 宣告(不是只寫在 controller)的 `drive-file`/`drive-image` 欄位** |
| `matrix:prune-tokens` | 刪掉已經不能用來認證的 token,`--limit` 控制每批筆數（預設 1000） |
| `matrix:rotate-encryption-key` | 產生新的 API 加密金鑰並把舊的標記為 `expire_time = now + grace`(`--grace` 秒數,預設 `encryption.grace-period`);同時刪除已經過完寬限期的舊金鑰。**任何一組前綴的加密開關是開的,上線之前就必須先跑過一次**——金鑰圈是空的時候,bootstrap 端點只會回 `encryption-unavailable` |
| `matrix:sync-translatable` | 掃描所有套件、所有 Model 的 translatable 欄位,幫缺少目前設定語言的欄位補上實體欄位（皆為 nullable,不回填） |
| `messages:dispatch` | 為每個有待送訊息的 channel 派送一個發送工作;任一 channel 設定壞掉就回非零 exit code |
| `messages:telegram-webhook` | 呼叫 Telegram `setWebhook` API,把 `{APP_URL}/{api-prefix}/telegram/webhook` 連同 `telegram.webhook-secret` 註冊上去;一次性維運操作,環境變了(換網域、換 ngrok 網址)要重跑 |

### 錯誤代碼

| 代碼 | 意思 |
|---|---|
| `actor-already-assigned` | 身分已設定，不可重複指派 |
| `captcha-request-failed` | 驗證碼請求失敗 |
| `data-conflicted` | 資料已被修改 |
| `data-in-use` | 這筆資料仍被其他資料參照，無法刪除 |
| `data-not-found` | 查無資料 |
| `directory-create-failed` | 無法建立目錄 |
| `drive-anchor-immutable` | home 目錄與群組目錄不能被搬移或丟進垃圾桶 |
| `encryption-unavailable` | 金鑰圈沒有可用的金鑰(還沒跑過 `matrix:rotate-encryption-key`) |
| `endpoint-not-found` | 端點不存在 |
| `file-too-large` | 檔案大小超過限制 |
| `geolocation-database-not-found` | 找不到地理位置資料庫檔案 |
| `geolocation-request-failed` | 地理位置查詢請求失敗 |
| `image-decode-failed` | 無法解析圖片 |
| `invalid-arrange-order` | 上下架選擇與資料不符 |
| `invalid-captcha-driver` | 驗證碼服務設定錯誤 |
| `invalid-cascade-relation` | 連動關聯必須是 hasOne、hasMany 或其 morph 形式 |
| `invalid-column-condition` | 欄位條件語法錯誤 |
| `invalid-column-expression` | 欄位運算式語法錯誤 |
| `invalid-drive-file` | 不是有效的雲端硬碟檔案 |
| `invalid-envelope` | 加密信封無法解讀:`kid` 不存在或已過期、GCM 驗證失敗、`ts` 超出容許誤差,或同一個信封被重送 |
| `invalid-filter-value` | 篩選值的格式不正確 |
| `invalid-geolocation-driver` | 地理位置服務設定錯誤 |
| `invalid-identity-model` | 身分 model 設定錯誤 |
| `invalid-identity-type` | 不支援此身分類型 |
| `invalid-ip-address` | IP 位址格式不正確 |
| `invalid-locale` | 語系不正確 |
| `invalid-message-channel` | 訊息管道設定錯誤 |
| `invalid-message-content` | 訊息內容不得為空 |
| `invalid-message-driver` | 訊息傳送器設定錯誤 |
| `invalid-message-provider` | 訊息供應商設定錯誤 |
| `invalid-message-receiver` | 收件對象不得為空 |
| `invalid-mime-type` | 不接受這種檔案類型 |
| `invalid-move-target` | 無法移動到該目的地 |
| `invalid-parent-relation` | 上層關聯必須是 belongsTo |
| `invalid-resource-token` | 資源代碼格式錯誤 |
| `invalid-sort-order` | 排序內容與資料不符 |
| `invalid-token` | 登入憑證無效或已過期 |
| `invalid-translation-driver` | 翻譯服務設定錯誤 |
| `invalid-type-resolver` | 複合欄位的型別判斷器設定錯誤 |
| `message-provider-has-no-driver` | 訊息供應商未設定傳送器 |
| `message-refused-by-provider` | 訊息被供應商拒絕 |
| `message-template-not-found` | 查無訊息樣板 |
| `mfa-already-enabled` | 雙因子驗證已經啟用 |
| `name-already-exists` | 這個名稱在此位置已經存在 |
| `nested-composite-not-supported` | 複合欄位不支援巢狀 |
| `permission-denied` | 權限不足 |
| `push-delivery-failed` | 推播通知無法送達任何訂閱 |
| `push-subscription-not-found` | 這個收件者沒有可用的推播訂閱 |
| `request-failed` | 請求無法處理 |
| `server-error` | 系統發生錯誤 |
| `too-many-requests` | 請求過於頻繁，請稍後再試 |
| `translation-request-failed` | 翻譯請求失敗 |
| `undeclared-action` | Action 未宣告 |
| `undeclared-model` | Model 未宣告欄位 |
| `unknown-message-channel` | 訊息管道未註冊 |
| `unknown-package` | 套件未註冊 |
| `unsupported-disk-driver` | disk 的 driver 不是 local |
| `validation-failed` | 輸入資料有誤 |

### 資料表

| 資料表 | 內容 |
|---|---|
| `base_auth_token` | 各身分的登入 token |
| `base_city` | 縣市 |
| `base_city_area` | 行政區 |
| `base_drive_node` | 雲端硬碟節點(資料夾與檔案共用同一張表);root/home/群組三個固定區域,軟刪除、無永久刪除 |
| `base_encryption_key` | API 傳輸加密的金鑰圈(EC P-256;私鑰以 `APP_KEY` 加密存放),`expire_time` 是輪替後的寬限期終點 |
| `base_file` | 上傳檔案(含去重雜湊與媒體資訊) |
| `base_group` | 後台群組 |
| `base_mail_log` | 郵件佇列與寄送結果 |
| `base_manipulation_log` | 稽核軌跡(誰改了哪一列的哪個欄位) |
| `base_member` | 前台會員 |
| `base_member_log` | 會員行為紀錄 |
| `base_menu` | 可線上維護的選單資料 |
| `base_page` | 可線上維護的前台頁面;`path` 是前台查找用的唯一網址,`data` 的欄位形狀由消費端在 `config('matrix.variants.page-data')` 接線決定 |
| `base_block` | 頁面底下的區塊,`page_id` 掛在 `base_page`;`type` 決定 `data` 的欄位形狀,由消費端在 `config('matrix.variants.block-data')` 接線定義。`type` 在 `insert` 時決定、之後不可改 |
| `base_block_item` | 區塊底下的子項目,`block_id` 掛在 `base_block`;型別跟著父區塊走,所以自己沒有 `type` 欄位 |
| `base_passkey_credential` | 後台帳號(`User`)的 Passkey 憑證(credential ID、COSE 公鑰、sign count 等) |
| `base_preference` | 各身分（user / member / vendor）各自一筆的個人化偏好,內容由前端決定 |
| `base_push_log` | 推播佇列與送達結果 |
| `base_push_subscription` | 會員的 Web Push 訂閱(endpoint / p256dh / auth) |
| `base_resource_override` | 資源後台線上編輯的覆蓋值 |
| `base_sms_log` | 簡訊佇列與寄送結果 |
| `base_telegram_log` | Telegram 佇列與送達結果 |
| `base_telegram_subscription` | 後台使用者(`User`)的 Telegram 綁定(user_id / chat_id / username) |
| `base_user` | 後台帳號 |
| `base_user_log` | 後台帳號的登入 / 改密碼紀錄 |
| `base_vendor` | 廠商 |
| `base_vendor_log` | 廠商行為紀錄 |
| `base_operator` | **檢視表**,把 user / member / vendor 併成一份「操作者」清單,給宿主查 `creator_id` 用。唯讀,不要對它寫入 |

---

## 給前端

### 請求形狀

**清單**（`POST admin/widget`）:

```json
{
  "filters": {
    "title": { "op": "contains", "value": "abc" },
    "status": { "op": "in", "value": [1, 2] },
    "create_time": { "op": "between", "from": "2026-01-01 00:00:00", "to": "2026-01-31 23:59:59" }
  },
  "sort": [{ "name": "create_time", "direction": "desc" }],
  "page": 1,
  "size": 10
}
```

- 每個欄位可以用哪些 `op`,由清單回應的 `columns[].op` 告訴你。送不被允許的欄位或運算子會**靜默忽略**,不會報錯。
- `between` 用 `from` / `to`（可以只給一邊）,其餘用 `value`。
- **`date` / `datetime` 欄位的值必須完全符合 `columns[].format` 給的格式**,一個字元都不能差:`date` 只收 `matrix.date-format`（出貨 `Y-m-d`）,`datetime` 只收 `matrix.datetime-format`（出貨 `Y-m-d H:i:s`）,兩者不互通。不符回 422 `invalid-filter-value`。所以查一整天要自己補時間:`from` 是 `00:00:00`、`to` 是 `23:59:59`——只送 `2026-01-31` 會被擋下,而**在舊版那樣送會漏掉當天所有資料**（`BETWEEN` 對 timestamp 欄位截在 `00:00:00`）。
- `page` 或 `size` 給 0 以下 = 不分頁,一次全回。

**詳情 / 編輯頁**（`POST admin/widget/{id}`）不需要 body。

**新增與更新**（`admin/widget/insert`、`admin/widget/{id}/update`）:欄位平鋪在最上層。

> **更新是全量覆寫,不是局部更新。** 每一個可寫欄位都會被加上 `present` 驗證規則 —— 不送就是 422,送 `null` 就是把它清成 null。前端要送完整的表單,不能只送改過的欄位。

**刪除**:`{"id": [1, 2, 3]}`,任一筆不存在整批失敗（`data-not-found`）。

**拖曳排序儲存**（`admin/widget/sort/save`）:`{"order": [3, 1, 2]}`,必須是完整集合,少一筆就是 `invalid-sort-order`。

### 回應形狀

清單回應含 `columns`（欄位描述）、`rows`、`pagination`、`actions`、`title`、麵包屑等。`columns[]` 每一項的三個維度要分開讀:

- `type` 是**資料型別**（`text` / `integer` / `float` / `boolean` / `date` / `datetime` / `json`）—— 決定驗證與比較。
- `presentation` 是**呈現方式**（`plain` / `hidden` / `select` / `multi-select` / `password` / `count` / `switch`）—— 決定畫面怎麼畫。宣告時給的字串若不是這七個,會**原樣送給前端**,那是自訂呈現方式的出口。
- `writable` 是**這一欄送回來會不會被寫入**。

一個「有選項的整數欄位」是 `type: integer` + `presentation: select`,三個維度都完整。

`type` 是 `date` 或 `datetime` 的欄位另外帶一個 `format`(例如 `YYYY-MM-DD`、`YYYY-MM-DD HH:mm:ss`,即 `matrix.date-format` / `matrix.datetime-format` 轉成前端慣用的日期格式代號),`rows`/`data` 裡該欄位的實際字串就是照這個格式輸出;其餘型別 `format` 是 `null`。

**不可寫有三種原因:`readonly` 宣告、`locked` 宣告、以及跨關聯或聚合欄位(`group.title`、`count(orders)`)。** 只有第一種在 `columns[]` 上另有 `readonly` 鍵看得出來,所以**前端要看 `writable`,不要看 `readonly`** —— 否則第二種會畫出一顆改了完全沒效果的輸入框,而使用者會看到「已儲存」。`virtual`(`+` 前綴)**不影響 `writable`**:虛擬欄位不落庫,但照樣可以收值交給 `guards` / 覆寫的 service 處理。

`writable: false` 的欄位不在 `present` 驗證的範圍內,送不送都可以;`writable: true` 的**每一個都必須出現在 body 裡**,那與上面「更新是全量覆寫」是同一件事的兩面。

**鎖定欄位是這條規則唯一的例外**:它的 `writable` 是 `false`,但它**必須**出現在 body 裡。把輸入框停用即可,值照送 —— 值本來就是伺服器在 `new` 回應的 `data` 裡給你的。

**action 的 `url` 不含前綴。** 回應給的是 `widget/{id}/update` 這種相對路徑,前端要自己接上 `admin/`。

### 語系

送 `Matrix-Locale: en` header。值必須在 `matrix.locales` 裡,否則退回應用程式的預設語系。

### `api/common/page`

前台頁面樹。匿名,body 帶 `path`(`base_page.path`,唯一)。查不到、或頁面不在 `enable_time` / `disable_time` 的區間內,一律回 404 `data-not-found`。

```json
{
  "id": 10000012,
  "path": "about-us",
  "title": "關於我們",
  "seo_title": "…",
  "seo_description": "…",
  "og_image": [{ "path": "@cms/og.jpg", "name": "og.jpg", "mime_type": "image/jpeg", "size": 92160, "width": 1200, "height": 630, "seconds": null }],
  "data": {},
  "children": [
    { "id": 10000031, "type": "none", "title": "首圖", "data": {}, "children": [
      { "id": 10000071, "title": "第一張", "data": {}, "children": [] }
    ] }
  ]
}
```

- **樹固定兩層**:頁面 → 區塊 → 子項目。子項目的 `children` 恆為 `[]`,留著是為了讓遞迴渲染不用分兩種節點型別。
- **`title` / `seo_title` / `seo_description` / `og_image` 依 `Matrix-Locale` 挑語系**,沒有 fallback:該語系沒填就是 `null`(`og_image` 是 `[]`)。
- **兩層都各自走排程**,排序依 `ranking`。區塊未啟用只是該區塊不出現,不會讓整頁消失;子項目同理。
- **`type` 是前端挑版型的唯一依據。** 套件本身不出貨任何型別;`config('matrix.variants.block-data')` 沒接上該型別時,`data` 原樣輸出儲存值,不做語系攤平。
- **三層的 `data` 空的時候是 `{}` 不是 `[]`**,不用分兩種型別處理。`api/common/menu` 的 `data` 同理。`og_image` 相反,它是清單,空的時候是 `[]`。
- **`data` 裡的多語系子欄位已經壓成單一值。** `{"caption": {"tw": …, "en": …}}` 出來會是 `{"caption": "…"}`,跟 `title` 一樣依 `Matrix-Locale` 挑好。非多語系的子欄位原樣不動。`api/common/menu` 的 `data` 也是同一個規則。
- **圖檔欄位給的是路徑不是網址**,自己組 `GET {api-prefix}/files/{path}`,縮圖加 `?size=thumb`。

### 驗證碼

**前後端必須成對。** 後端的 `matrix.admin-captcha-provider` 決定驗哪一種憑證,前端的 `config.captcha` 決定畫哪一種元件——兩邊對不上的話登入一律 422。每個 driver 會宣告自己要哪些憑證欄位(`captcha-image` 要 `token` + `code`,Turnstile / reCAPTCHA 只要 `token`,`captcha-none` 兩個都不要),所以前端少送的時候錯誤是 `validation-failed` 並在 `fields` 裡指名缺的是 `token` 還是 `code`,而不是含糊的驗證碼錯誤。

| 後端 `matrix.admin-captcha-provider` | 前端 `initConfig({ captcha })` | 還要準備什麼 |
|---|---|---|
| `captcha-none`(出貨預設) | `MxCaptchaNone`(出貨預設) | 無 |
| `captcha-image` | `MxCaptchaImage` | `ext-gd`(含 FreeType)、跨請求共用的 cache store |
| `captcha-turnstile` | `MxCaptchaTurnstile` | site key + `cfg('captcha-turnstile.secret')` |
| `captcha-recaptcha` | `MxCaptchaRecaptcha` | site key + `cfg('captcha-recaptcha.secret')` |

這個鍵只管後台登入。前台(member / vendor)的登入端點本來就要你自己做,要驗證碼就自己呼叫 `app(CaptchaService::class)->driver('captcha-turnstile')`——bundle 名必填,套件沒有替前台準備設定鍵,所以前後台要不要用同一個 driver 完全由你決定。

前端那半邊在宿主應用的 `src/router/index.js`:

```js
initConfig({
    captcha: MxCaptchaTurnstile,
    captchaSiteKey: "0x4AAAAAAA..."
});
```

site key 是**公開值**,本來就會出現在前端 bundle 裡;secret 相反,只放後端。兩個 cfg bundle 的 `secret` **出貨都是空字串**,自己填——空著不會放行,而是每次驗證都失敗、登入一律擋下。不想改檔就用 `base_resource_override` 存(見[驗證碼服務掛掉時怎麼進後台](#驗證碼服務掛掉時怎麼進後台)),那張表同樣吃得下 secret。

⚠️ **`action` 兩邊都寫死成 `login`。** 前端的 `turnstile.js` / `recaptcha.js` 裡是常數、不吃設定,後端 `cfg('captcha-{turnstile,recaptcha}.action')` 的出貨值也是 `login`。只改後端那一個會讓每次驗證都失敗——要改就兩邊一起改,不然別動。

#### Cloudflare Turnstile

1. Cloudflare 儀表板 → Turnstile → 新增 widget,**Widget Mode 選 Managed**。
2. 把要跑後台的網域加進 widget 的 hostname 清單(本機開發另外加 `localhost`)。
3. Site Key 填進前端的 `captchaSiteKey`,Secret Key 填進 `cfg('captcha-turnstile.secret')`。

前端 render 時帶 `appearance: "interaction-only"`,所以多數訪客什麼都不會看到,只有被判定可疑的才跳挑戰。這個外觀設定**只對 Managed 與 Non-Interactive 生效**——widget 建成 Invisible 的話它不起作用。

#### Google reCAPTCHA

前端載的是 `enterprise.js`、呼叫 `grecaptcha.enterprise.execute()`,所以要建的是 **reCAPTCHA Enterprise 的 score-based key**(傳統 v2 那種勾選框已經無法申請新金鑰)。

1. Google Cloud Console → **Fraud Defense**(reCAPTCHA 現在的所在)→ Keys → 新增 website key,類型選 **Score-based**。
2. 網域加進該 key 的 domain 清單。
3. Key ID 就是 site key,填進前端的 `captchaSiteKey`。
4. Secret 要另外拿:Key details → **Integration** 分頁 → **Use Legacy Key**,對話框裡那把才是 `cfg('captcha-recaptcha.secret')` 要的值。

⚠️ 第 4 步是最容易卡住的地方。Enterprise 的 key 詳情頁**預設不顯示 secret key**,因為 Google 假設你會用 `CreateAssessment` API(要 service account)。這個 driver 走的是舊的 `siteverify` 端點,吃的是那把 legacy secret——每一把 Enterprise key 都有,只是藏在 Use Legacy Key 後面。

分數門檻是 `cfg('captcha-recaptcha.threshold')`,出貨 `0.5`。調它之前先看[已知限制與取捨](#安全)裡關於「分數不夠的真人沒有自證管道」那條。

### 傳輸加密

三組前綴各有各的開關,出貨值是 `admin` 開、`vendor` 開、`api` 關。開關關著的那一組一切照舊,以下完全不存在。開著的那一組,**該前綴底下所有 POST 端點**(除了宣告 `#[Action(encrypted: false)]` 的 action)的 request 與 response body 都是加密信封,明文請求一律回 `invalid-envelope`。

後台前端只打 `admin`,所以下面一律以它為例。

**每開一個分頁做一次**:`POST encryption-key`(明文,不在任何前綴底下)拿 `{"kid", "public_key"}`,`public_key` 是 base64 的 SPKI DER,用 `crypto.subtle.importKey('spki', ..., {name: 'ECDH', namedCurve: 'P-256'}, false, [])` 匯入。

**每次呼叫做一次**:產生一組**用完即丟**的 P-256 金鑰對 → 跟伺服器公鑰做 ECDH → `HKDF-SHA256` 衍生出這次專用的 AES-256-GCM 金鑰,參數是 `salt` 空、`info` 為 UTF-8 的 `matrix-api-v1`、長度 256 bit。

```json
{ "kid": "...", "epk": "<base64 SPKI>", "ts": 1757000000, "iv": "<base64 12 bytes>", "ct": "<base64 密文+tag>" }
```

- `ts` 是**秒**為單位的 Unix 時間戳,與伺服器時間差超過 `cfg('encryption.window')` 就是 `invalid-envelope`。
- `epk` 是一次性公鑰,同時當作防重放的 nonce:**同一個 `epk` 只能用一次**,重送第二次是 `invalid-envelope`。
- AAD 是 `request|{METHOD} {path}|{ts}` 的 UTF-8 位元組。`path` 是**完整的 URL 路徑去掉開頭斜線**(即瀏覽器的 `new URL(...).pathname`,應用程式裝在子目錄時包含子目錄),例如 `request|POST admin/user/insert|1757000000`。少了它,同一份密文可以被原封不動搬去別支端點。
- `ct` 是 `crypto.subtle.encrypt` 的原始輸出(密文後面接 16 bytes 的 GCM tag),不用自己拆。

**回應**是 `{"iv", "ct"}`,用**同一把**衍生金鑰、新的 IV、AAD 換成 `response|{METHOD} {path}|{ts}`。解開之後才是平常那個 `{"success": ...}` 信封,錯誤信封也一樣被加密。

**唯一的例外是解密失敗**:中介層自己擋下來的錯誤沒有金鑰可以加密,會以**明文**回 `{"success": false, "code": 400, "error": "invalid-envelope", "message": "..."}`。前端在解密前必須先認這個形狀,不要無條件把回應丟進 `crypto.subtle.decrypt`。

---

## 沿用套件的 lint

**選用。** 前提是你的 app 也照套件那套風格公約寫:`<?php //>` 開頭、class 主體前後空一行、成員字母序、禁 `??`、禁 `saveQuietly` 一類繞過 model 事件的寫法。不打算全套照用就不要接 —— 這些檢查沒有開關。（違規訊息會引用 `CLAUDE.md`,那是套件自己的風格公約文件,沒隨套件出貨,規則摘要見本節末的表格。）

套件的 `require-dev` 不會遞移到你的專案,工具要自己裝:

```bash
composer require --dev friendsofphp/php-cs-fixer larastan/larastan
```

### 1. `composer.json`

```json
"scripts": {
    "format": "php-cs-fixer fix",
    "lint": [
        "php-cs-fixer fix --dry-run --diff",
        "@php vendor/matrix-platform/laravel-base/bin/style-check.php"
    ],
    "stan": "phpstan analyse --memory-limit=1G"
}
```

`style-check.php` 不傳參數時檢查當前工作目錄,而 composer script 的 cwd 就是 `composer.json` 所在的目錄,所以不用給路徑。要檢查別的目錄就傳第一個參數:`@php vendor/.../bin/style-check.php packages/foo`。

### 2. `.php-cs-fixer.php`

finder 指你自己的目錄,rules 引用套件那一份:

```php
<?php //>

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/config', __DIR__ . '/database', __DIR__ . '/resources', __DIR__ . '/routes', __DIR__ . '/tests'])
    ->name('*.php')
    ->notPath('#(^|/)menu/[^/]+\.php$#');

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setRiskyAllowed(false)
    ->setRules(require __DIR__ . '/vendor/matrix-platform/laravel-base/.php-cs-fixer.rules.php');
```

`notPath` 那行不要省。選單 bundle（`menu/*.php`,含 `resources/i18n/*/menu/*.php`）的縮排跟著選單層次走,`array_indentation` 會把它拉平。

### 3. `phpstan.neon`

這裡沒有共用檔:套件的設定只有 `level: 8` 一個參數,抄一行比多一層 include 划算。

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 8
    paths:
        - app
        - tests
    tmpDir: build/phpstan
```

### style-check 檢查什麼

腳本掃 `app`、`bin`、`config`、`database`、`resources`、`routes`、`src`、`tests`,不存在的目錄跳過,所以套件與宿主共用同一份預設。規則按路徑前綴分派:

| 檢查 | 生效範圍 |
|---|---|
| `<?php //>` 開頭、class 主體前後空行、`fn` 後空一格、禁 `??` 與 `??=` | 全部 |
| 多行陣列的尾逗號 | `config/`、`resources/` 要加,其餘不加 |
| 方法鏈超過兩個 `->` 要逐行展開 | `app/`、`src/`、`tests/` |
| 類別成員字母序 | 除 `tests/` 之外全部 |
| 禁用繞過 model 事件的寫法 | 除 `bin/`、`config/`、`resources/` 之外全部 |

程式碼放在這幾個目錄之外（例如模組化的 `modules/`）就完全不會被掃到,前綴對不上的部分也會靜默跳過對應檢查 —— 沒有警告。

---

## 已知限制與取捨

每一條都是**刻意的決定**或**已知的缺陷**,不是待辦清單。放在這裡是因為它們會影響你的部署決定。

### 安全

| 事實 | 你要做什麼 |
|---|---|
| **`admin/i18n/get` 是匿名端點,任何人可以讀走任何一份翻譯檔** —— 包含 `template/*`（郵件與簡訊樣板全文）。登入畫面需要它,所以不能關 | 不要在 i18n 資源裡放非公開內容 |
| **驗證碼端點匿名且沒有節流** | 需要的話自己加 |
| **驗證碼的 site key 是公開的,不設 `hostnames` 就擋不住別人拿它在自己的網站養 token** —— 攻擊者把你的 site key 放進自己的頁面,訪客產生的 token 有 `success`、有正確的 `action`(那是他自己設的)、分數還是真人的高分,拿來打你的登入端點會完全穿透驗證碼這一關 | 設 `captcha-recaptcha.hostnames` / `captcha-turnstile.hostnames` 為後台登入頁的網域(可多個)。**出貨是空的 = 不檢查**,因為預設開啟會讓既有部署升級後全部登不進去,而症狀只會是「驗證碼錯誤」。這件事在本套件特別要緊——見下面那條,登入節流擋不住拿一組密碼掃一堆帳號,驗證碼是應用層唯一能讓 spraying 變貴的東西 |
| **`captcha-recaptcha` 是 Enterprise 分數式的,低於 `captcha-recaptcha.threshold` 的真人沒有任何自證管道** —— 它不跳挑戰,分數不夠就是進不去(傳統的 v2 隱形式已無法申請新金鑰) | 用這顆就要求管理員都註冊 passkey(passkey 登入完全不經過驗證碼),否則唯一的出路是下方的 cfg override。要「可疑時才要求互動、互動完就過」請改用 `captcha-turnstile` |
| **第三方驗證碼 driver(`captcha-turnstile` / `captcha-recaptcha`)是 fail-closed 的** —— 對方服務打不通時回 `captcha-request-failed`,登入一律擋下。而且每一次被擋都吃掉一格登入節流(`afterCallback` 只計失敗),五次之後同一組 IP+帳號連 `too-many-requests` 都會拿到 | 接受它,但事先知道逃生門怎麼走:見下方〈驗證碼服務掛掉時怎麼進後台〉。**不要改成「打不通就放行」**,那等於給攻擊者一個把驗證碼關掉的開關 |
| **`api/common/*` 三個端點匿名且沒有節流**,`base_menu.data` 與 `base_page.data` 的內容會原樣出現在回應裡 | 不要在這些 `data` 欄位放非公開資料。`api/common/page` 另外會把區塊引用的雲端硬碟檔案路徑吐出來,而 `api/files/{path}` 對 drive 檔案匿名可取、不看 `privilege` —— 區塊放什麼圖就等於公開發佈什麼圖 |
| **登入節流的鍵是「IP + 帳號」** —— 同一個 IP 換帳號就換一份配額,擋不住拿一組密碼掃一堆帳號 | 要擋就在應用層之外做（WAF / 反向代理） |
| **Passkey 登入端點沒有帳號欄位,節流退化成近似純 IP** | 比密碼登入更粗放的取捨,若濫用明顯可考慮改用 `IP + credential_id 前綴` 當節流鍵 |
| **`matrix.passkey-rp-id` 的 fallback 是當次請求的主機名稱,只在後台前端與此 API 同源時才正確**;RP ID 一旦設錯或事後變更,所有已註冊 passkey 會**全部永久失效,無遷移路徑**(WebAuthn 規格的密碼學綁定特性) | 若前後端分離部署在不同網域,啟用 passkey 前務必明確設定 `matrix.passkey-rp-id`,不要依賴 fallback |
| **傳輸加密的三個開關(`matrix.{admin-api,api,vendor-api}-encryption`)都是明確開關,沒有自動偵測**;`crypto.subtle` 只在 secure context(HTTPS 或 `localhost` / `127.0.0.1`)存在,用區網 IP 走純 HTTP 的開發環境打不開它 | 那種環境把開關關掉,並且清楚知道**那台機器沒有應用層加密**。不要讓前端「偵測不到就自動退回明文」——那等於給攻擊者一個降級開關 |
| **防重放的去重表存在 `Cache`**,一台機器記下的 `epk` 只有那台知道 | 多機部署時 `CACHE_STORE` 必須是跨機共享的(Redis),用 `file` / `array` 等單機快取的話,同一份密文換一台機器就能重送一次 |
| **加密只保護 body,不改變 token 的傳法**(`Authorization` header / `matrix-user` cookie 還是原樣),也不取代 HTTPS | 它防的是「TLS 在反向代理就終止、之後那段是明文」這個威脅,不是 HTTPS 的替代品 |
| **私鑰以 `APP_KEY` 加密後存在 `base_encryption_key`** | 資料庫與 `APP_KEY` 同時外洩,等於過去 `encryption.grace-period` 之內錄下來的流量都能解開。輪替頻率照你的合規要求定 |
| **`base_auth_token.token` 是明文** | 資料庫外洩等於所有人的登入狀態外洩,備份與存取控制要照這個等級處理 |
| **cookie 的 `secure` 跟隨 `config('session.secure')`** | 生產環境務必設成 true,否則 token 會在明文連線上傳 |
| **上傳不檢查型別**（`cfg('file.mime-patterns')` 出貨空白 = 全部放行） | 要限制就設 `mime-patterns`（正則,空白分隔,**不能含逗號或分號** —— 那是分隔字元） |
| **上傳不沿用使用者送來的副檔名** | 磁碟上是 `年月/32 碼隨機名`,沒有副檔名,所以放在公開 disk 也不會被 web server 當程式執行。下載的 Content-Type 取自 `base_file.mime_type`,檔名取自 `base_file.name`（原始檔名,含副檔名）,使用者端無感 |
| **上傳不限制大小**（`cfg('file.max-size')` 出貨 `0`）。真正的上限是 PHP 的 `upload_max_filesize` / `post_max_size` | 兩層都要調。只調 cfg 沒用,只調 ini 的話使用者會拿到 422 而不是 `file-too-large` |
| 檔案的 `privilege` 決定存哪個 disk（`0` 公開 / `1` 私有）。下載一律走 `admin/file/download`,**要登入** | 公開 disk 若做了 `storage:link`,那些檔案就有公開 URL —— 這是 disk 設定的結果,不是套件的存取控制。另外「要登入」**只是登入** —— `admin/file/*` 掛的是 `user-api`,沒有 `permission-api`,所以**選單權限完全為空的管理員**也能下載 / 改名任何 path 的檔案。要更細的檔案授權,自己在該路由前加 middleware |
| **前台沒有任何檔案端點** | 前台要上傳就自己呼叫 `FileService::upload()`,並自己決定權限與限制 |
| **雲端硬碟(`drive/*`)上傳不檢查型別或大小**,沒有等同 `file.max-size` / `file.mime-patterns` 的設定 | 要限制就自己在 `DriveService::upload()` 前面加檢查 |
| **`drive/*` 沒有選單節點,只掛 `user-api`**(比照 `admin/auth/passwd`、`admin/file/*`)——任何登入的後台 User 都能呼叫,不需要選單授權 | 節點層級的 owner/群組存取完全交給 `DrivePermissionService` 這一層把關,不是靠選單權限 |
| **`manipulation-log/query`、`schedule/toggle` 同樣沒有專屬選單節點、只掛 `user-api`**,但跟上面兩列不同——這兩支端點呼叫時會依請求帶的 `prefix` 找出目標資料實際所屬的既有選單節點,動態呼叫 `AdminPermission::permits()` 檢查 `query`/`update` 權限,行為上等同「借用」該資料原本的選單授權,不是完全不設防 | `prefix` 必須是某個已掛載 `ActionRoutes::mount()` 的 CRUD 資源的路由前綴(如 `group`);對應不到就回 `unsupported-model`,對應得到但沒權限一律 403 |
| **`manipulation-log/query` 的回應帶一份 `options`**(欄位名 → 選項清單,與 CRUD `columns[].options` 同構),而且**含已被軟刪除的關聯**,那些選項的 `deleted` 是 `true` | 歷史紀錄裡存的是 id,要翻得出名字就不能像表單那樣把軟刪除的關聯濾掉。CRUD 自己的 `new` / `{id}` / 清單 / 匯出維持原樣(只給沒刪除的),不受影響 |
| **`drive/{id}/delete` 只軟刪除目標本身,不遞迴子項目**;但被刪節點底下**沒被動到**的子孫會變成整體不可操作——`DrivePermissionService::allowed()` 往上爬錨點時遇到已軟刪除的祖先就直接判定沒有權限,**`User::ROOT` 也不例外** | 這是刻意的設計:不用遞迴刪除/還原,單純靠「祖先鏈斷在已軟刪除的節點」讓整個子樹自然變成不可操作;`restore()` 也只還原目標本身,把祖先救回來,子孫的可操作性就自動恢復。**但「看不看得到」是另一回事**——`drive/trashed` 跟 `drive/{id}/path` 用的是 `visible()`,爬的時候會穿過軟刪除的祖先繼續找,所以子孫依然會出現在垃圾桶列表、路徑依然查得到,只是在祖先還原之前 `restore()` 會報 `permission-denied` |
| **欄位 DSL 是開發者輸入**,識別字會被插值進 SQL | 絕對不要把使用者輸入拼進 `$lists` / `$updates` |
| **權限白名單只覆蓋 CRUD 的寫入路徑**。`replicate()`、`setRawAttributes()`、query builder 的 `update()` 都繞得過去 | 白名單防的是請求輸入,不是程式碼 |
| **訊息樣板的變數會原樣進入 HTML,不逸出** | 把使用者輸入當變數傳進去之前自己逸出。開放樣板編輯 = 把那個人當成信任的 HTML 作者 |
| **`admin/translation` 送出去翻譯之前會保護佔位符**,但只保護**獨立出現**的 `{name}` / `{{name}}`——HTML 屬性值裡的(`<a href="?token={code}">`)原樣送給翻譯服務,翻回來可能已經被改掉 | 要翻的字串裡別把佔位符寫進屬性值。保護的作法是先換成一個標記元素、翻完再換回來;標記被翻譯服務改寫到認不出來時,整次翻譯回 `available: false`(**不會**回傳夾著標記殘骸的譯文),應用程式日誌記 `translation.failed` / `placeholder-lost` |

#### 驗證碼服務掛掉時怎麼進後台

`config/matrix.php` **出貨模板**裡沒有任何 `env()`,所以照抄的話改 provider 等於改檔加重新部署 —— 凌晨三點那不是逃生門。**資源後台的 cfg 編輯介面也不能用,因為它要先登入**,而登入正是被擋住的那件事。

第一道逃生門其實很便宜:那個檔案是**你自己應用的**(套件只 `mergeConfigFrom` 預設值,見[安裝](#3-建立-configmatrixphp)),所以你大可自己寫成 `'admin-captcha-provider' => env('MATRIX_ADMIN_CAPTCHA_PROVIDER', 'captcha-none')`,事後只要改 `.env`。代價是若有 `config:cache` 就得再跑一次 `php artisan config:cache` —— 零改碼,但不是零操作。

沒事先鋪這條線、而且現在不能部署的話,才輪到下面這個:

實際可用的是 DB override(`Resources::overrides()` 會把 `base_resource_override` 疊在 cfg 預設值上):

```sql
INSERT INTO base_resource_override (bundle, data, create_time)
VALUES ('cfg/captcha-turnstile', '{"driver":"MatrixPlatform\\Captcha\\NoneDriver"}', NOW());
```

⚠️ **那是兩個反斜線,不是四個,而且 `create_time` 不能省。** PostgreSQL 的 `standard_conforming_strings` 預設是 `on`,字串字面量裡的反斜線不再被跳脫,所以 JSON 的 `\\` 剛好解成一個反斜線;寫成四個會存進 `MatrixPlatform\\Captcha\\NoneDriver`,那不是合法的類別名稱,`resolve_driver()` 會改丟 `invalid-captcha-driver` —— **逃生門反而把門鎖得更死**。`create_time` 是 NOT NULL 且沒有預設值,漏掉會直接被資料庫擋下(這個至少是大聲失敗)。

```bash
php artisan matrix:clear-resource-cache
```

驗證碼立刻放行,零部署、零改檔。**清快取那一步不能省** —— `overrides()` 是 `rememberForever`。

同一張表也可以只調參數而不整個關掉,例如把 reCAPTCHA 的分數門檻放寬:`{"threshold":0.1}`。

⚠️ 服務恢復之後,先前被擋掉的失敗仍然佔著登入節流的配額,可能還要等一個 `admin.login-throttle-window` 或自己清掉 rate limiter。

⚠️ 新增或改動 cfg bundle 的檔案之後也要跑同一個指令 —— `Resources::merge()` 把「這個 bundle 不存在」也一起 `forever` 快取了,不清的話會一直拿到 `invalid-captcha-driver`。

### 規模與效能

| 事實 | 影響 |
|---|---|
| **匯出會把整張表（套完篩選後）載進記憶體** | 沒有任何上限。大表要匯出請自己做背景任務 |
| **`_id` 欄位的下拉選項成本與被參照資料表的列數成正比** | 被參照的表上千列時,那份選項就是整個清單回應的主要體積 |
| **`$sortable` 只該開在資料量有上限的資源上** | 拖曳排序會把整組載進來跑重排演算法 |
| 重排用最長遞增子序列找錨點,tie-break 是次佳選擇 | 最壞情況會把「只改一列」變成「整組重編」 |
| `contains` / `endsWith` 走 `ILIKE`,本來就用不到 B-tree;**`startsWith` 也走 `ILIKE`,同樣用不到** | 要就自己加 `lower(col)` 運算式索引或 `pg_trgm` GIN |
| **`api/common/city`、`menu` 與 `page` 沒有快取,而且是 POST** | CDN / proxy 快取不適用,要快取只能做在應用層 |
| **三個 disk 設定只能用 local driver** | 縮圖產生與 IP 資料庫讀取走的是原生檔案函式(`is_file`、`mkdir`、`file_put_contents`、`rename`),它們吃的是 `Storage::disk()->path()` 的回傳值——那個值對非 local driver 是**相對路徑**,會相對於行程當下的工作目錄解析,而 queue worker、artisan 指令與 php-fpm 的工作目錄各不相同,同一份縮圖可能寫在三個地方、讀的時候都找不到,而且全程沒有錯誤。套件本身也沒有安裝任何遠端 flysystem adapter。設成別的 driver 會在取用 disk 時直接回 `unsupported-disk-driver` |
| **`Resources` 是 singleton** | Octane 這類長生命週期行程改了設定要重啟才看得到。**訊息 worker 例外**——`SendMessageJob` 每次執行前會先丟掉已快取的 bundle,所以在資源後台改了 cfg(SMTP 主機/帳密、`interval` 等)下一封信就生效,不必重啟 worker |
| 級聯刪除會逐筆取出實例再刪（為了稽核） | 成本隨子資料列數成長 |

### 資料生命週期

| 事實 | 你要做什麼 |
|---|---|
| **`matrix:prune-tokens` 只清 `base_auth_token`** | 另外八張只增不減的表要自己來:`base_manipulation_log`、`base_user_log`、`base_member_log`、`base_vendor_log`、`base_mail_log`、`base_sms_log`、`base_push_log`、`base_telegram_log` |
| 前四張只有 `create_time`(沒有 `update_time`);後四張兩個都有 | 清理判準只能用 `create_time` |
| **`base_mail_log` / `base_sms_log` / `base_push_log` / `base_telegram_log` 只能刪終端狀態**(成功 / 失敗) | `Scheduled` 是還沒送出的排程,刪掉等於取消一封信 |
| **`base_file` 不要用時間清** | 刪列不刪磁碟檔會漏儲存空間,而去重讓一筆記錄可能被多處引用 —— 套件答不出「誰可以刪」。**例外**:drive-linked 的 `base_file`(`path` 以 `@` 開頭)由 `matrix:prune-drive-files` 即時掃描 CRUD 資料表判斷是否還有引用,可以安全清除 |
| **`base_drive_node` 完全沒有永久刪除**——`drive/{id}/delete` 只是軟刪除(`deleted_at`),node 與實體檔案永遠不會真的消失 | 這是刻意的決定,不是漏做垃圾清除;資料庫與磁碟用量只會隨使用量增加,規劃容量時要算進去 |
| **調大 `token-idle-minutes` 不會復活已經被清掉的 token** | 要調大就先調、再跑 prune |
| `token-idle-minutes` 的 `min:1` 驗證**只擋資源後台** | 自己在 `resources/cfg/admin.php` 寫 `0` 不受檢查,結果是全員登出、prune 清空整張表 |

### 行為細節

#### 請求與交易

| 事實 | 說明 |
|---|---|
| **每個 `#[Action]` 都要有選單節點** | 漏一個,那個端點對所有人 403,包含 ROOT |
| **每一個 action 都跑在一個交易裡** | `BaseController::callAction()` 用 `DB::transaction()` 包住整個動作。要在 rollback 之後仍然執行的副作用（寄信、打第三方、刪檔）請註冊到 `RollbackCallbacks`,不要直接做 |
| **`#[Action]` 會沿繼承鏈繼承** | 覆寫 action 不需要重新宣告 attribute |
| **`#[Action(encrypted: false)]` 讓那支 action 不受所屬前綴的加密開關約束** | 呼叫方不可能封信封的端點要標記它:出貨已標的是 telegram webhook、兩支 multipart 上傳(`file/upload`、`drive/{id}/upload`)與 `encryption-key`。自己的上傳端點也要自己標,漏標的症狀是開關一開就變 `invalid-envelope` |
| **篩選值的格式與型別都會驗證** | op 要的是單一值卻送陣列(`eq` / `contains` / `between` 的 from、to 等)、`in` / `notIn` 的清單裡有陣列,一律回 422 `invalid-filter-value`。以前這幾種格式有的靜默回**全量**、有的靠 binding 攤平湊出一個結果。值也要對得上欄位型別:`integer` 要整數或純數字字串、`float` 要 numeric、`boolean` 要 `true`/`false`/`0`/`1`、`date` 要完全符合 `matrix.date-format`、`datetime` 要完全符合 `matrix.datetime-format`（兩者不互通,也不收 `now`、`tomorrow`、`+1 day` 這類 PostgreSQL 或 PHP 認得的相對值,以及 `2026-02-31` 這種會被 PHP 悄悄進位的日期）,不符同樣回 422。**這一道是必要的**——不擋的話那個值會原樣進 SQL,PostgreSQL 丟 `22P02`,而 action 跑在交易裡,整個交易會進 aborted 狀態(`25P02`),該請求之後每一個查詢都失敗,真正的原因還被蓋掉。型別依的是欄位**宣告**的型別(DSL 標註 → 聚合函式 → `#[Declared]` 的 definition → model cast),都沒有就當 `text` 不檢查。欄位或 op 不被允許仍是靜默忽略（行為不變）,`in` 清單裡的 null 也照舊（合法 SQL,永不匹配） |
| **`get` / `update` / `delete` 會自動加上父層條件** | 巢狀資源不會誤動別人家的資料 |
| **樂觀鎖要自己呼叫** | `BaseModel::lock()` 會重讀該列並逐欄比對,值被別人改過就回 `data-conflicted`。CRUD 引擎不會自動幫你呼叫 |
| **`AdminPermission` 在信封範圍外解析會靜默失敗** | 在 web 路由、console、queue job 裡解析它,`ServiceException` 不會被回報 |

#### 權限與帳號

| 事實 | 說明 |
|---|---|
| **權限樹只認四個動作** | `query` / `insert` / `update` / `delete`。選單節點上寫別的 `tag`（例如 `system`）不會變成可勾選的權限項目 |
| **手寫進資料庫的權限,形狀錯了會被靜默丟掉** | 存進去的形狀必須是 `{"路徑": {"動作": true}}`。值不是 true 的項目在下一次寫入時就消失,而且不會有任何錯誤 |
| **權限的寫入是「範圍內修訂」** | 編輯者只能授出自己有的權限,也洗不掉自己碰不到的;白名單以外的權限（例如維運用 SQL 寫的）會被保留 |
| **`guard` 是疊加的,套件先、宿主後** | 宿主看到的是已經過濾的值,而且無法覆蓋套件自己的 guard（例如「不能刪自己」） |
| **`user` 的 `copy` 與 `export` 同受等級範圍約束** | 與 `get` / `update` / `delete` 一樣,管不到的帳號一律 `data-not-found`、匯不出來。`copy` 只要宿主加選單節點就開啟,`export` 還要子類化把 `$exportable` 翻成 true;`sort` 對 `user` 不可用（`base_user` 沒有 `ranking` 欄位）。`group` 不受等級範圍約束 |
| **`password` 的 key 一定要送,值可以留空** | 完全不送 key 會回 422 `present`(更新是全量覆寫,漏送等於前端壞了)。送 `null` 或空字串則不寫入 —— 編輯保留原密碼與 session,新增建出沒有密碼、登不進來的帳號。只有非空值才必須符合 `admin.password-pattern`,編輯成功後撤銷該帳號全部 session |
| **預先雜湊的值當密碼會被原樣存進去** | 政策只有 `admin.password-pattern` 一道,而 bcrypt hash 通過它(60 碼、含英文與數字)。`hashed` cast 對已雜湊的值不再雜湊,所以前端若先雜湊再送,該帳號之後得拿 hash 字串當密碼才能登入,而且沒有任何錯誤訊息 |
| **`whereActive()` 把 `enable_time` 為 NULL 的列一律當成未啟用** | 對 `user` 的表徵是「後台建的帳號沒填啟用時間就登不進來」（`enable_time` 要非 null 且已到,而表單沒把它設成必填,症狀是「帳號或密碼錯誤」,看不出真正原因）;對 `base_menu` 的表徵是 `api/common/menu`（匿名端點）**靜默回空選單** —— 沒有報錯路徑,debug 起來毫無線索 |

#### 複製、匯出與排序

| 事實 | 說明 |
|---|---|
| **複製會沿用來源的 `ranking`** | 除非那個資源開了 `$sortable`,否則不會自我修復 |
| 複製時 `$generators` 管的欄位（建立時間、建立者等）**重新產生**,不照抄 | —— |
| **級聯複製只接受 `hasOne` / `hasMany` 及其 morph 形式** | `belongsToMany` 不支援 |
| **匯出明寫 `'id'` 匯不出主鍵** | 要主鍵請寫 `'key=id'` |
| **`$exports = []` 是「沒有欄位」,不是「退回清單欄位」** | —— |
| **`$hidden` 只對 root model 的欄位有效** | join 進來的別名不受它保護 |
| **匯出回應的 `columns[]` 不含 `op` / `sortable` / `options`** | 前端要知道能篩什麼,必須先呼叫清單端點 |
| **`base_city_area.ranking` 與 `base_ranking` 序列不同量級** | 後台第一次拖曳排序就會把整組重編 |
| **jsonb 欄位會宣稱自己可排序** | 引擎沒有把 Json 型別排除在排序之外。對它排序不會壞,但結果沒有意義 |
| **巢狀資源只被「直接父層」的外鍵 scope,祖父層的路由參數完全不驗證** | `CrudService::prepared()` 只取 `Subject::foreign()` 那一個外鍵。所以 `admin/page/999/block/31/item` 即使 31 號區塊不屬於 999 號頁面,也照樣回 31 號的子項目。權限是按路徑 pattern 給的不是按值,沒有越權讀取,但**網址會說謊**:麵包屑的 `label` 沿真實關聯查 DB,顯示的是 31 號區塊真正的父頁面,跟網址對不上 |
| **兩層以上的巢狀資源,`count()` 的鑽取路徑要自己寫** | `ColumnResolver::path()` 推導出前綴後會經過 `Subject::generic()`,那會把**每一個**佔位符都換成 `{id}`。一層巢狀沒事(`city/{id}/area`),兩層就變成 `page/{id}/block/{id}/item` —— 兩個 `{id}` 分不出誰是誰。解法是在 `$lists` 用陣列語法明寫:`['name' => 'count(items)', 'path' => 'page/{page_id}/block/{id}/item']`,明寫的值直接送出,不經過 `generic()` 也不查 `Menus::has()`。前端是拿**那一列**去填佔位符的,而列本身帶著自己的外鍵(`page_id`)與 `id`,所以具名佔位符填得滿。代價是這個字串與路由、選單三處必須人工保持一致,沒有任何機制會驗證,寫錯的症狀是點數字連到 404 |
| **`$counts` 在設了 `$lists` 或 `$updates` 的 controller 上會靜默失效** | `CrudController::listing()` 只有在 `$lists` 為 null **且** `$updates` 為空時才把 `counted()` 併進清單。設了其中任何一個,`$counts` 就完全沒有作用,也不會有任何警告。要 count 欄位就直接寫進 `$lists`(`'count(items)'`),不要用 `$counts` |
| **自我參照的樹,巢狀資源必須掛在與關聯名同名的路由段下** | `count(children)` 的鑽取路徑是這樣推出來的:`Subject::path()` 遇到自我參照會停在 alias(`menu`),引擎改用關聯名 `children` 去選單裡找 `menu/{任意參數}/children`,找到才有 `path`。掛成別的名字(例如 `menu/{parent_id}/sub`)不會壞,但 `path` 會是 `null`,前端的數字就點不進去。`admin/menu` 只列頂層,子層一律走 `admin/menu/{parent_id}/children`,每一層都回到同一個 pattern,深度不限 |

#### 資料寫入與稽核

| 事實 | 說明 |
|---|---|
| **上傳是內容去重的** | `hash` + `size` + `privilege` + `usage` 相同就是同一筆,回傳既有紀錄,`name` 保留**第一次**上傳的檔名。磁碟上的檔案被外部刪掉時不會命中去重,會重新寫一份新紀錄 |
| **對套件的 model 下批次 `delete()` / `update()` 會靜默失去稽核** | query builder 的批次操作不觸發 model 事件。要稽核就取出實例逐筆處理 |
| **稽核紀錄的 `after` 是 accessor 之後的值** | 你掛在可追蹤欄位上的 accessor 從此決定稽核內容 |
| **`Operator` 掛在檢視表上** | 唯讀,不要對它 `save()` |
| **`base_drive_node` 沒有 `deleter_id` 欄位** | API 回應的 `deleted_by` 是即時查 `base_manipulation_log`(`data_type`/`data_id` 對上該節點、`type = Deleted` 的最新一筆)反查出來的,只在 `deleted_at` 非空時才查一次;未刪除的節點固定回 `null`,不打這張表 |

#### 選單與回應契約

| 事實 | 說明 |
|---|---|
| 麵包屑的 `label` 在多層資源下可能與直覺不同,`title` 三層都正確 | 照舊版行為,已用測試釘住 |
| **`context.{父層}_id` 是字串**,而 `rows` 裡的 id 是整數 | 前端比對時要注意型別 |
| **`getMenuNodes()` 的每個節點一定有 `group` 與 `tag`**（可能是 `false` / `null`） | 舊版是沒有就不輸出。用 `empty()` 判斷不受影響 |
| **`base_menu` 的迴圈與孤兒節點會被靜默丟掉** | 資料完整性由你負責,不會有錯誤訊息 |

---

## 從舊版升級

這是下一個大版本,**不保證與舊版相容**。已知要動的:

| 項目 | 怎麼改 |
|---|---|
| **權限的儲存位置** | 舊版存在 Storage 的 JSON 檔（`permission/User/{id}`、`permission/Group/{id}`）,新版存在 `base_user.permissions` / `base_group.permissions` jsonb 欄位。要寫一次性搬移,並順便檢查孤兒 path（選單裡已經不存在的節點） |
| **`matrix:prune` 改名** | 新名字是 `matrix:prune-tokens`,**沒有別名**,cron 要改 |
| **`admin-menus` 的語義** | 從路徑清單變成 bundle 名稱清單（`'app base'` 這種形式） |
| **多選欄位變成可篩選** | payload 會帶 `op: 'in'`,舊版是不可篩選。前端會據此渲染篩選器 |
| **匯出的 `columns[].type`** | 舊版是一個混合欄位,新版拆成 `type` + `presentation` |
| **驗證錯誤的鍵名** | 舊版是 `errors`,新版統一成 `error`（slug）+ `fields`（欄位明細） |
| **匯入沒有出貨** | 舊版的匯入功能在新版不存在 |
| **上傳的儲存 path 不再帶副檔名** | 舊資料不用動（既有帶副檔名的 path 照樣找得到、下載得到）。若有程式直接從 `base_file.path` 解析副檔名,改讀 `mime_type` 或 `name` |
