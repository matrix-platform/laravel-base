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
- [訊息](#訊息) —— [送訊息](#送訊息)、[派送與 worker](#派送與-worker)、[自訂訊息通道](#自訂訊息通道)
- [參考](#參考) —— [端點](#端點)、[設定鍵](#設定鍵)、[cfg 設定鍵](#cfg-設定鍵)、[主控台指令](#主控台指令)、[錯誤代碼](#錯誤代碼)、[資料表](#資料表)
- [給前端](#給前端) —— [請求形狀](#請求形狀)、[回應形狀](#回應形狀)、[語系](#語系)
- [沿用套件的 lint](#沿用套件的-lint) —— 選用,共用同一套風格檢查
- [已知限制與取捨](#已知限制與取捨) —— [安全](#安全)、[規模與效能](#規模與效能)、[資料生命週期](#資料生命週期)、[行為細節](#行為細節)
- [從舊版升級](#從舊版升級)

---

## 套件不提供什麼

先講界線,因為這些是刻意的決定,不是還沒做:

| 沒有 | 說明 |
|---|---|
| **前台登入端點** | 套件出貨 `member-api` / `vendor-api` middleware 與 `AuthToken::issue()`,**但沒有任何前台登入 controller**。前台的登入流程、驗證碼策略、密碼規則由宿主決定 |
| **API 文件產生器** | 不出貨 Swagger / OpenAPI。端點是 `#[Action]` 反射掛載的,要文件請從 attribute 反射產生,不要掃註解 |
| **排程註冊** | 套件不呼叫 `Schedule::command()`。兩個需要週期執行的指令由宿主自己排 |
| **cache / queue driver 的選擇** | 套件用 `Cache` 與 `Queue` 門面,不指定 driver。驗證碼要跨請求共用的 cache,訊息派送要每條 queue 恰好一個 worker（見[派送與 worker](#派送與-worker)） |
| **匯入** | 匯出有,匯入沒有 |
| **檔案清理** | `base_file` 與磁碟上的檔案永遠不會被自動刪除。去重讓一筆記錄可能被多處引用,套件答不出「誰可以刪」 |
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
| `ext-gd`，**且編譯時帶 FreeType** | 後台登入的驗證碼用 `imagettftext()` 畫字 | `admin/auth/captcha` 回 500,而登入**強制**要驗證碼 —— 完全登不進去 |
| 一個**跨請求共用**的 cache store | 驗證碼答案寫在 cache,下一個請求才比對 | `CACHE_STORE=array` 的話每次登入都是 `invalid-captcha`。多台機器沒有共用 cache 會隨機失敗 |

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

**驗證:** `base_user`、`base_group`、`base_auth_token` 等 16 張表存在。

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

### 8. 註冊排程（如果要用訊息或 token 清理）

套件不註冊排程。在你的 `routes/console.php`:

```php
Schedule::command('matrix:prune-tokens')->daily()->withoutOverlapping();
Schedule::command('messages:dispatch')->everyMinute()->withoutOverlapping();
```

`withoutOverlapping()` 不是裝飾:兩個 prune 行程同時跑會互相搶同一批列。

訊息還需要 queue worker。mail 與 sms 各有自己的 queue,而**每條 queue 只能有一個 worker**:

```
php artisan queue:work --queue=messaging-mail
php artisan queue:work --queue=messaging-sms
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

這不是風格偏好:客戶端的防火牆會擋掉非 200 的回應。`error` 欄位就是 i18n key,`message` 是後端已經翻好的字串 —— 前端可以直接顯示 `message`,也可以拿 `error` 自己對照。

**信封的範圍是套件的兩個路由前綴。** `admin/` 與 `api/` 底下打不到的路徑、用錯方法的請求,都會回信封格式的 404。**這兩個前綴之外的請求不受影響** —— 你自己的網頁 404 還是 Laravel 原本的樣子。

代價要知道:因為兜底路由吃下所有方法,**「方法用錯」不再回 405,而是回 404 信封**。前端分不出「網址拼錯」與「方法用錯」。

### 2. 全部端點都是 POST

沒有一個 GET。讀取(清單、詳情)也是 POST。用 GET 打會拿到 404 信封。

### 3. 身分、middleware,以及順序

| 別名 | 作用 |
|---|---|
| `envelope-api` | 把例外收斂成信封。**必須掛在最外層** |
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

合併是**逐 key 遞迴**的（排前的覆蓋排後的、key 對 key）,所以覆蓋一個選單節點時,低優先層那個節點多出來的 key（`icon`、`tag` 之類）會滲進合併結果,沒辦法個別移除。要**整個移除**一個節點,把該 key 的值設成 `null` —— 節點消失、端點對所有人 403、群組離開權限樹。但移除節點只擋端點與**新**授權:**已經授出的權限仍然有效**,因為授權檢查讀的是 `base_user.permissions` / `base_group.permissions` 存的 JSON,不是選單樹。

---

## 開一個自己的功能

以一個 `Widget` 為例,**六個步驟,少一個都不會動**。

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

`Metadata` 的第一個參數是 **alias,它必須等於選單節點的路徑前綴**;第二個是「這一列叫什麼」的欄位（麵包屑與排序頁會用）。第三個參數可以指定父層關聯,巢狀資源才需要。第四個參數 `ranking` 可指定排序用的欄位名稱,`CrudController` 會用它推導預設的 `$sorting`/`$sortable`;第五、六個參數 `enable`/`disable` 標記上下架時間欄位,目前只是宣告能力,尚無消費端。

沒有 `#[Declared]` 的 model 一進 CRUD 端點就是 `undeclared-model`。

### 4. Controller

```php
class WidgetController extends CrudController {

    protected string $model = Widget::class;

    protected ?array $lists = ['title', 'category.title=category_title', 'enable_time'];

    protected array $updates = ['*title', 'category_id:select', 'enable_time', 'disable_time'];

}
```

`*` 開頭代表必填。`=` 後面是別名。`.` 走關聯。`:` 後面是型別或呈現方式。

### 5. 路由 —— 必須掛在 `admin` 前綴之下

```php
Route::middleware(['envelope-api', 'locale-api'])->group(function () {
    Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
        Route::middleware(['user-api', 'permission-api'])->group(function () {
            ActionRoutes::mount('widget', WidgetController::class);
        });
    });
});
```

**前綴不對,`AdminPermission` 認不出這是套件的路由,全部 403。**

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
| 登出的語義、驗證碼生命週期、密碼規則 | 全部由你決定 |

---

## 訊息

### 送訊息

`MailService` 與 `SmsService` 的公開介面只有 `schedule()`:

```php
app(MailService::class)->schedule(now(), 'alice@example.com', 'welcome', ['name' => 'Alice']);
app(SmsService::class)->schedule(now()->addHour(), '0912345678', 'otp', ['code' => '123456']);
```

參數是 `($at, $to, $template = null, $vars = [], $options = [])`,回傳寫進 `base_mail_log` / `base_sms_log` 的那一列。樣板檔案放 `resources/i18n/{語系}/template/{name}.php`。

| 事實 | 說明 |
|---|---|
| 回傳的列是 `Scheduled`,不是已送出 | 真正的送出在 worker。`schedule()` 只負責寫列 + 通知有東西要送 |
| `$at` 到期了才派工 | 未來時間只寫列,等 `messages:dispatch` 那一輪撈到 |
| **樣板可以是 `null`,provider 不行** | 樣板要嘛自己指名 `provider`,要嘛 `$options` 給。都沒有就 `invalid-message-provider`;供應商沒設 `driver` 就 `message-provider-has-no-driver` |
| `$options` 覆蓋樣板的渲染結果 | 只認 `provider`、`subject`、`title`、`content` 四個 key。值是 `null` **不算**覆蓋 |
| mail 的寄件者是當下快照 | `sender` 寫入時從 `cfg('{provider}.from-address')` 取,之後改設定不影響已排程的訊息 |
| **取消與重送要自己做** | 套件不提供。取消 = 把 `Scheduled` 的列改成 `Failed`;重送 = 用同樣的參數再 `schedule()` 一次 |

### 派送與 worker

| 事實 | 說明 |
|---|---|
| **一個發送工作只送一封,節奏由 worker 的序列性決定** | 工作的單位是 channel。它撈這個 channel 最前面一筆（`schedule_time` 再 `id`）、送出、睡掉該供應商的 `interval`、然後派下一個工作接手;撈不到就結束,不派後繼。sleep 佔住的是那條 queue 唯一的 worker,所以兩次送出之間一定隔了 `interval`,不管佇列裡有幾個工作 —— 套件不記錄「上次幾點送的」,沒有任何節流狀態。`messages:dispatch` 只是鏈條斷掉時的安全網（`EXISTS` 檢查,有東西在等就派一個工作） |
| **每條 queue 最多一個 worker** | 節流靠的是 worker 的序列性,所以多開一個 worker 就是速率翻倍。內建的 mail 與 sms 各有自己的 queue(`messaging-mail`／`messaging-sms`),要開兩個 worker;**漏開一條就是那條 channel 全部停在 `Scheduled`**,而 `messages:dispatch` 每分鐘照樣回成功。套件不偵測 worker 存活 —— 那歸行程監管（systemd／supervisor／Horizon）,你為了跑 `queue:work` 本來就需要它們 |
| **`interval` < `--timeout` < `retry_after`** | 套件無法幫你算這三個數字:工作在派送時只知道 channel,還不知道會送到哪個供應商。`--timeout`（預設 60）要大於「最大的 `interval` + 單筆送出耗時」,connection 的 `retry_after` 要再大於它 |
| **同一個 channel 嚴格 FIFO** | 送出順序就是 `schedule_time` 的順序,不分供應商。所以一個供應商的 `interval` 也會延後排在它後面的其他供應商的訊息 |
| **隊頭送不出去會卡住整個 channel** | 排程時 `schedule()` 就會擋掉沒有 driver 的供應商,所以這只發生在「排程之後設定才壞掉」:`driver` 被拿掉、改成不是 driver 的類別、或 cfg bundle 消失。工作會失敗（進 `failed_jobs`）、不動任何記錄,而那一筆還在隊頭 —— 後面的訊息（包含其他供應商的）都送不出去。排程每分鐘再派一次,所以壞多久就累積多少筆 `failed_jobs`。要隔離就給那個供應商自己的 channel（也就是自己的 queue 與 worker） |
| **設定壞掉的失敗只在 `failed_jobs` 看得到** | `ServiceException::report()` 回 `false`,所以那種例外不進 Laravel 的 exception handler,Sentry 那類收不到 |
| **送出失敗會寫一筆 `Log::error`** | 這裡說的是 driver 真的被呼叫之後才失敗:記錄標成 `Failed`,鏈條照樣往下走。事件名 `messaging.{channel}.failed`,context 帶 channel、provider、訊息 id 與寫進 `error` 欄位的內容。`error` 可能含供應商回應（`response` 欄位本來就存完整回應）,log 的保存政策要照這個前提設 |
| **worker 硬掉在送出中間會重送** | 送出成功但寫回失敗、或行程被 SIGKILL／OOM 砍在 driver 呼叫途中時,那一列還是 `Scheduled`,下一個工作會再送一次。優雅重啟（SIGTERM）不受影響,Laravel 會讓當前工作跑完。套件選的是「寧可重送也不漏送」,而且**沒有重試次數上限** |
| queue connection 設成 `sync` | 送出會變成同步執行,`interval` 照樣生效（在同一個行程裡睡）,但鏈條會變成遞迴呼叫 |

### 自訂訊息通道

| 事實 | 說明 |
|---|---|
| 註冊點是 `config/matrix.php` 的 `messaging.channels`,**但那是巢狀 key** | 宣告 `messaging` 會整個取代掉內建的 mail / sms |
| **每個 channel 都必須宣告 `queue`** | 沒宣告就是 `invalid-message-channel`,那個 channel 完全不能用。加一個 channel 就是加一條 queue 加一個 worker |
| 每個供應商還要一份 `resources/cfg/{provider}.php` | **`driver` 是必填** —— 沒有它 `schedule()` 當場回 `message-provider-has-no-driver`。其餘的鍵照 [cfg 設定鍵](#cfg-設定鍵)裡 `gmail` 與 `mitake` 兩組的形狀寫 |
| **供應商與樣板名稱要跨 channel 唯一** | —— |
| 樣板必須指名 `provider` | 否則 `schedule()` 當場回 `invalid-message-provider` |

---

## 參考

以下表格是**契約**。守門測試（`tests/Feature/ReadmeTest.php`）會把第一欄的識別字拿去跟實際的路由表、設定、指令、資料表對照 —— 表格寫錯或程式改了沒回來改表,測試就會紅。

### 端點

全部是 `POST`。路徑省略前綴（`admin`、`api` 與 `vendor` 分別來自 `matrix.admin-api-prefix`、`matrix.api-prefix` 與 `matrix.vendor-api-prefix`）。

| 方法 | 路徑 | 需要 |
|---|---|---|
| POST | `admin/auth/captcha` | 匿名 |
| POST | `admin/auth/login` | 匿名（有節流） |
| POST | `admin/auth/logout` | 登入 |
| POST | `admin/auth/passwd` | 登入 |
| POST | `admin/auth/profile` | 登入 |
| POST | `admin/i18n/get` | **匿名** |
| POST | `admin/file/upload` | 登入 |
| POST | `admin/file/download` | 登入 |
| POST | `admin/file/update` | 登入 |
| POST | `admin/drive/root` | 登入 |
| POST | `admin/drive/home` | 登入 |
| POST | `admin/drive/group` | 登入 |
| POST | `admin/drive/{id}` | 登入 |
| POST | `admin/drive/{id}/children` | 登入 |
| POST | `admin/drive/{id}/folder` | 登入 |
| POST | `admin/drive/{id}/upload` | 登入 |
| POST | `admin/drive/{id}/download` | 登入 |
| POST | `admin/drive/{id}/rename` | 登入 |
| POST | `admin/drive/{id}/move` | 登入 |
| POST | `admin/drive/{id}/path` | 登入 |
| POST | `admin/drive/{id}/delete` | 登入 |
| POST | `admin/drive/trashed` | 登入 |
| POST | `admin/drive/{id}/restore` | 登入 |
| POST | `admin/user` | 授權 |
| POST | `admin/user/new` | 授權 |
| POST | `admin/user/insert` | 授權 |
| POST | `admin/user/{id}` | 授權 |
| POST | `admin/user/{id}/update` | 授權 |
| POST | `admin/user/{id}/copy` | 授權 |
| POST | `admin/user/delete` | 授權 |
| POST | `admin/user/export` | 授權 |
| POST | `admin/user/sort` | 授權 |
| POST | `admin/user/sort/save` | 授權 |
| POST | `admin/user/arrange` | 授權 |
| POST | `admin/user/arrange/save` | 授權 |
| POST | `admin/user/preference/get` | 登入 |
| POST | `admin/user/preference/save` | 登入 |
| POST | `admin/group` | 授權 |
| POST | `admin/group/new` | 授權 |
| POST | `admin/group/insert` | 授權 |
| POST | `admin/group/{id}` | 授權 |
| POST | `admin/group/{id}/update` | 授權 |
| POST | `admin/group/{id}/copy` | 授權 |
| POST | `admin/group/delete` | 授權 |
| POST | `admin/group/export` | 授權 |
| POST | `admin/group/sort` | 授權 |
| POST | `admin/group/sort/save` | 授權 |
| POST | `admin/group/arrange` | 授權 |
| POST | `admin/group/arrange/save` | 授權 |
| POST | `admin/resource/cfg` | 授權 |
| POST | `admin/resource/cfg/get` | 授權 |
| POST | `admin/resource/cfg/update` | 授權 |
| POST | `admin/resource/i18n` | 授權 |
| POST | `admin/resource/i18n/get` | 授權 |
| POST | `admin/resource/i18n/update` | 授權 |
| POST | `admin/resource/i18n/menu` | 授權 |
| POST | `admin/resource/i18n/menu/get` | 授權 |
| POST | `admin/resource/i18n/menu/update` | 授權 |
| POST | `admin/resource/i18n/model` | 授權 |
| POST | `admin/resource/i18n/model/get` | 授權 |
| POST | `admin/resource/i18n/model/update` | 授權 |
| POST | `admin/resource/i18n/options` | 授權 |
| POST | `admin/resource/i18n/options/get` | 授權 |
| POST | `admin/resource/i18n/options/update` | 授權 |
| POST | `admin/resource/i18n/template` | 授權 |
| POST | `admin/resource/i18n/template/get` | 授權 |
| POST | `admin/resource/i18n/template/update` | 授權 |
| POST | `api/common/city` | 匿名 |
| POST | `api/common/menu` | 匿名 |
| POST | `api/member/preference/get` | 登入 |
| POST | `api/member/preference/save` | 登入 |
| POST | `vendor/preference/get` | 登入 |
| POST | `vendor/preference/save` | 登入 |

「授權」= 登入 + 該選單節點的權限。`user` / `group` 的 `export`、`copy`、`sort` 端點存在但**套件出貨的選單沒有對應節點**,所以預設對所有人 403 —— 那三個動作在套件自己的兩個 controller 上是關閉的。

### 設定鍵

`config/matrix.php`。改任何一個都要記得 `mergeConfigFrom` 只合併頂層 key。

| 鍵 | 出貨值 | 說明 |
|---|---|---|
| `matrix.admin-api-prefix` | `'admin'` | 後台路由前綴 |
| `matrix.admin-menus` | `'base'` | 要載入哪些選單 bundle,空白分隔,排前面的覆蓋排後面的 |
| `matrix.api-prefix` | `'api'` | 前台路由前綴 |
| `matrix.drive-disk` | `'local'` | 雲端硬碟實體檔案的 disk,獨立於 `file-*-disk`,永遠不對外公開,只透過 `drive/{id}/download` 讀取 |
| `matrix.file-private-disk` | `'local'` | 非公開檔案的 disk |
| `matrix.file-public-disk` | `'public'` | 公開檔案的 disk |
| `matrix.locales` | `'tw en'` | 允許的語系,對應 `resources/i18n/{語系}/` |
| `matrix.member-model` | `Member::class` | 會員 model,宿主可換成自己的 |
| `matrix.messaging` | 見範本 | channel 註冊。每個 channel 都要有 `model` 與 `queue`。**巢狀 key,宣告就整份取代** |
| `matrix.packages` | `'app base'` | 資源疊層順序 |
| `matrix.resource-cfg` | `[]` | 資源後台開放編輯的 cfg bundle 白名單,**空 = 全部不開放** |
| `matrix.resource-i18n` | `[]` | 同上,一般翻譯 |
| `matrix.resource-i18n-menu` | `[]` | 同上,選單標題 |
| `matrix.resource-i18n-model` | `[]` | 同上,欄位標題 |
| `matrix.resource-i18n-options` | `[]` | 同上,下拉選項 |
| `matrix.resource-i18n-template` | `[]` | 同上,訊息樣板 |
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
| `admin.password-pattern` | `'/^(?=.*\d)(?=.*[a-zA-Z]).{8,}$/'` | 自助改密碼、`matrix:passwd` 與使用者表單共用的密碼規則 |
| `admin.token-idle-minutes` | `30` | 後台 token 閒置多久失效 |
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
| `file.max-size` | `0` | 上傳大小上限,**0 = 不限制** |
| `file.mime-patterns` | `''` | 型別白名單(正則,空白 = 不檢查) |
| `drive.deduplicate` | `true` | 開啟後,雲端硬碟上傳內容雜湊相同的檔案會共用同一份實體檔案,不重複寫入 |
| `drive.trash-default-days` | `30` | `drive/trashed` 預設只列出這幾天內的垃圾桶項目,可用 `days`/`all` 參數放寬,不影響 `restore` |
| `system.date-format` | `'Y-m-d'` | 日期顯示格式 |
| `system.datetime-format` | `'Y-m-d H:i:s'` | 日期時間顯示格式 |

`gmail` 與 `mitake` 是出貨的供應商 bundle。加一個供應商就是加一份 `resources/cfg/{名稱}.php`,鍵的形狀照上面兩組。

### 主控台指令

| 指令 | 作用 |
|---|---|
| `matrix:passwd` | 設定後台帳號密碼,建立管理員的唯一官方入口 |
| `matrix:prune-tokens` | 刪掉已經不能用來認證的 token,`--limit` 控制每批筆數（預設 1000） |
| `matrix:sync-translatable` | 掃描所有套件、所有 Model 的 translatable 欄位,幫缺少目前設定語言的欄位補上實體欄位（皆為 nullable,不回填） |
| `messages:dispatch` | 為每個有待送訊息的 channel 派送一個發送工作;任一 channel 設定壞掉就回非零 exit code |

### 錯誤代碼

| 代碼 | 意思 |
|---|---|
| `actor-already-assigned` | 身分已設定，不可重複指派 |
| `data-conflicted` | 資料已被修改 |
| `data-not-found` | 查無資料 |
| `drive-anchor-immutable` | home 目錄與群組目錄不能被搬移或丟進垃圾桶 |
| `endpoint-not-found` | 端點不存在 |
| `file-too-large` | 檔案大小超過限制 |
| `invalid-arrange-order` | 上下架選擇與資料不符 |
| `invalid-cascade-relation` | 連動關聯必須是 hasOne、hasMany 或其 morph 形式 |
| `invalid-column-condition` | 欄位條件語法錯誤 |
| `invalid-column-expression` | 欄位運算式語法錯誤 |
| `invalid-filter-value` | 篩選值的格式不正確 |
| `invalid-identity-model` | 身分 model 設定錯誤 |
| `invalid-identity-type` | 不支援此身分類型 |
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
| `message-provider-has-no-driver` | 訊息供應商未設定傳送器 |
| `message-refused-by-provider` | 訊息被供應商拒絕 |
| `message-template-not-found` | 查無訊息樣板 |
| `name-already-exists` | 這個名稱在此位置已經存在 |
| `permission-denied` | 權限不足 |
| `request-failed` | 請求無法處理 |
| `server-error` | 系統發生錯誤 |
| `too-many-requests` | 請求過於頻繁，請稍後再試 |
| `undeclared-model` | Model 未宣告欄位 |
| `unknown-message-channel` | 訊息管道未註冊 |
| `unknown-package` | 套件未註冊 |
| `validation-failed` | 輸入資料有誤 |

### 資料表

| 資料表 | 內容 |
|---|---|
| `base_auth_token` | 各身分的登入 token |
| `base_city` | 縣市 |
| `base_city_area` | 行政區 |
| `base_drive_node` | 雲端硬碟節點(資料夾與檔案共用同一張表);root/home/群組三個固定區域,軟刪除、無永久刪除 |
| `base_file` | 上傳檔案(含去重雜湊與媒體資訊) |
| `base_group` | 後台群組 |
| `base_mail_log` | 郵件佇列與寄送結果 |
| `base_manipulation_log` | 稽核軌跡(誰改了哪一列的哪個欄位) |
| `base_member` | 前台會員 |
| `base_member_log` | 會員行為紀錄 |
| `base_menu` | 可線上維護的選單資料 |
| `base_preference` | 各身分（user / member / vendor）各自一筆的個人化偏好,內容由前端決定 |
| `base_resource_override` | 資源後台線上編輯的覆蓋值 |
| `base_sms_log` | 簡訊佇列與寄送結果 |
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
    "create_time": { "op": "between", "from": "2026-01-01", "to": "2026-01-31" }
  },
  "sort": [{ "name": "create_time", "direction": "desc" }],
  "page": 1,
  "size": 10
}
```

- 每個欄位可以用哪些 `op`,由清單回應的 `columns[].op` 告訴你。送不被允許的欄位或運算子會**靜默忽略**,不會報錯。
- `between` 用 `from` / `to`（可以只給一邊）,其餘用 `value`。
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

**不可寫有三種原因:`readonly` 宣告、`virtual`(`+` 前綴)、以及跨關聯或聚合欄位(`group.title`、`count(orders)`)。** 只有第一種在 `columns[]` 上另有 `readonly` 鍵看得出來,所以**前端要看 `writable`,不要看 `readonly`** —— 否則後兩種會畫出一顆改了完全沒效果的輸入框,而使用者會看到「已儲存」。

`writable: false` 的欄位不在 `present` 驗證的範圍內,送不送都可以;`writable: true` 的**每一個都必須出現在 body 裡**,那與上面「更新是全量覆寫」是同一件事的兩面。

**action 的 `url` 不含前綴。** 回應給的是 `widget/{id}/update` 這種相對路徑,前端要自己接上 `admin/`。

### 語系

送 `Matrix-Locale: en` header。值必須在 `matrix.locales` 裡,否則退回應用程式的預設語系。

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
| **`api/common/*` 兩個端點匿名且沒有節流**,`base_menu.data` 的內容會原樣出現在回應裡 | 不要在 `base_menu.data` 放非公開資料 |
| **登入節流的鍵是「IP + 帳號」** —— 同一個 IP 換帳號就換一份配額,擋不住拿一組密碼掃一堆帳號 | 要擋就在應用層之外做（WAF / 反向代理） |
| **`base_auth_token.token` 是明文** | 資料庫外洩等於所有人的登入狀態外洩,備份與存取控制要照這個等級處理 |
| **cookie 的 `secure` 跟隨 `config('session.secure')`** | 生產環境務必設成 true,否則 token 會在明文連線上傳 |
| **上傳不檢查型別**（`cfg('file.mime-patterns')` 出貨空白 = 全部放行） | 要限制就設 `mime-patterns`（正則,空白分隔,**不能含逗號或分號** —— 那是分隔字元） |
| **上傳不沿用使用者送來的副檔名** | 磁碟上是 `年月/32 碼隨機名`,沒有副檔名,所以放在公開 disk 也不會被 web server 當程式執行。下載的 Content-Type 取自 `base_file.mime_type`,檔名取自 `base_file.name`（原始檔名,含副檔名）,使用者端無感 |
| **上傳不限制大小**（`cfg('file.max-size')` 出貨 `0`）。真正的上限是 PHP 的 `upload_max_filesize` / `post_max_size` | 兩層都要調。只調 cfg 沒用,只調 ini 的話使用者會拿到 422 而不是 `file-too-large` |
| 檔案的 `privilege` 決定存哪個 disk（`0` 公開 / `1` 私有）。下載一律走 `admin/file/download`,**要登入** | 公開 disk 若做了 `storage:link`,那些檔案就有公開 URL —— 這是 disk 設定的結果,不是套件的存取控制。另外「要登入」**只是登入** —— `admin/file/*` 掛的是 `user-api`,沒有 `permission-api`,所以**選單權限完全為空的管理員**也能下載 / 改名任何 path 的檔案。要更細的檔案授權,自己在該路由前加 middleware |
| **前台沒有任何檔案端點** | 前台要上傳就自己呼叫 `FileService::upload()`,並自己決定權限與限制 |
| **雲端硬碟(`drive/*`)上傳不檢查型別或大小**,沒有等同 `file.max-size` / `file.mime-patterns` 的設定 | 要限制就自己在 `DriveService::upload()` 前面加檢查 |
| **`drive/*` 沒有選單節點,只掛 `user-api`**(比照 `admin/auth/passwd`、`admin/file/*`)——任何登入的後台 User 都能呼叫,不需要選單授權 | 節點層級的 owner/群組存取完全交給 `DrivePermissionService` 這一層把關,不是靠選單權限 |
| **`drive/{id}/delete` 只軟刪除目標本身,不遞迴子項目**;但被刪節點底下**沒被動到**的子孫會變成整體不可操作——`DrivePermissionService::allowed()` 往上爬錨點時遇到已軟刪除的祖先就直接判定沒有權限,**`User::ROOT` 也不例外** | 這是刻意的設計:不用遞迴刪除/還原,單純靠「祖先鏈斷在已軟刪除的節點」讓整個子樹自然變成不可操作;`restore()` 也只還原目標本身,把祖先救回來,子孫的可操作性就自動恢復。**但「看不看得到」是另一回事**——`drive/trashed` 跟 `drive/{id}/path` 用的是 `visible()`,爬的時候會穿過軟刪除的祖先繼續找,所以子孫依然會出現在垃圾桶列表、路徑依然查得到,只是在祖先還原之前 `restore()` 會報 `permission-denied` |
| **欄位 DSL 是開發者輸入**,識別字會被插值進 SQL | 絕對不要把使用者輸入拼進 `$lists` / `$updates` |
| **權限白名單只覆蓋 CRUD 的寫入路徑**。`replicate()`、`setRawAttributes()`、query builder 的 `update()` 都繞得過去 | 白名單防的是請求輸入,不是程式碼 |
| **訊息樣板的變數會原樣進入 HTML,不逸出** | 把使用者輸入當變數傳進去之前自己逸出。開放樣板編輯 = 把那個人當成信任的 HTML 作者 |

### 規模與效能

| 事實 | 影響 |
|---|---|
| **匯出會把整張表（套完篩選後）載進記憶體** | 沒有任何上限。大表要匯出請自己做背景任務 |
| **`_id` 欄位的下拉選項成本與被參照資料表的列數成正比** | 被參照的表上千列時,那份選項就是整個清單回應的主要體積 |
| **`$sortable` 只該開在資料量有上限的資源上** | 拖曳排序會把整組載進來跑重排演算法 |
| 重排用最長遞增子序列找錨點,tie-break 是次佳選擇 | 最壞情況會把「只改一列」變成「整組重編」 |
| `contains` / `endsWith` 走 `ILIKE`,本來就用不到 B-tree;**`startsWith` 也走 `ILIKE`,同樣用不到** | 要就自己加 `lower(col)` 運算式索引或 `pg_trgm` GIN |
| **`api/common/city` 與 `menu` 沒有快取,而且是 POST** | CDN / proxy 快取不適用,要快取只能做在應用層 |
| **`Resources` 是 singleton** | queue worker、Octane 這類長生命週期行程改了設定要重啟才看得到 |
| 級聯刪除會逐筆取出實例再刪（為了稽核） | 成本隨子資料列數成長 |

### 資料生命週期

| 事實 | 你要做什麼 |
|---|---|
| **`matrix:prune-tokens` 只清 `base_auth_token`** | 另外六張只增不減的表要自己來:`base_manipulation_log`、`base_user_log`、`base_member_log`、`base_vendor_log`、`base_mail_log`、`base_sms_log` |
| 前四張只有 `create_time`（沒有 `update_time`）;後兩張兩個都有 | 清理判準只能用 `create_time` |
| **`base_mail_log` / `base_sms_log` 只能刪終端狀態**（成功 / 失敗） | `Scheduled` 是還沒送出的排程,刪掉等於取消一封信 |
| **`base_file` 不要用時間清** | 刪列不刪磁碟檔會漏儲存空間,而去重讓一筆記錄可能被多處引用 —— 套件答不出「誰可以刪」 |
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
| **篩選值的格式會驗證** | op 要的是單一值卻送陣列(`eq` / `contains` / `between` 的 from、to 等)、`in` / `notIn` 的清單裡有陣列,一律回 422 `invalid-filter-value`。以前這幾種格式有的靜默回**全量**、有的靠 binding 攤平湊出一個結果。欄位或 op 不被允許仍是靜默忽略（行為不變）,`in` 清單裡的 null 也照舊（合法 SQL,永不匹配） |
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
