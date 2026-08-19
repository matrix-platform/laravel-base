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
- [參考](#參考) —— 端點、設定、指令、錯誤代碼、資料表
- [給前端](#給前端)
- [已知限制與取捨](#已知限制與取捨)
- [從舊版升級](#從舊版升級)

---

## 套件不提供什麼

先講界線,因為這些是刻意的決定,不是還沒做:

| 沒有 | 說明 |
|---|---|
| **前台登入端點** | 套件出貨 `member-api` / `vendor-api` middleware 與 `AuthToken::issue()`,**但沒有任何前台登入 controller**。前台的登入流程、驗證碼策略、密碼規則由宿主決定 |
| **API 文件產生器** | 不出貨 Swagger / OpenAPI。端點是 `#[Action]` 反射掛載的,要文件請從 attribute 反射產生,不要掃註解 |
| **排程註冊** | 套件不呼叫 `Schedule::command()`。兩個需要週期執行的指令由宿主自己排 |
| **cache / queue driver 的選擇** | 套件用 `Cache` 與 `Queue` 門面,不指定 driver。驗證碼、訊息節流、訊息派送對 driver 有要求（見[已知限制](#已知限制與取捨)) |
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
| `ext-pdo_pgsql` | 上面那條的驅動,`composer.json` 沒有宣告它 | 連不上資料庫 |
| `ext-gd`，**且編譯時帶 FreeType** | 後台登入的驗證碼用 `imagettftext()` 畫字 | `admin/auth/captcha` 回 500,而登入**強制**要驗證碼 —— 完全登不進去 |
| 一個**跨請求共用**的 cache store | 驗證碼答案寫在 cache,下一個請求才比對 | `CACHE_STORE=array` 的話每次登入都是 `invalid-captcha`。多台機器沒有共用 cache 會隨機失敗 |

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
        'queue' => 'default',
        'channels' => [
            'mail' => ['model' => MailLog::class],
            'sms' => ['model' => SmsLog::class],
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

指令會問兩次密碼。密碼規則來自 `cfg('admin.password-pattern')`,出貨值是「至少 8 碼、含英文與數字」。

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
                'title' => new Definition(ColumnType::Text),
                'category_id' => new Definition(ColumnType::Integer)
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

`Metadata` 的第一個參數是 **alias,它必須等於選單節點的路徑前綴**;第二個是「這一列叫什麼」的欄位（麵包屑與排序頁會用）。第三個參數可以指定父層關聯,巢狀資源才需要。

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

**欄位標題找不到時的行為是「看起來正常但可能不對」** —— 先退到 `model/default.php` 的通用標籤（`create_time` 之類的共用欄位都在那裡),再退到字面 `{title}`。所以漏翻譯不一定看得出來。

---

## 參考

以下表格是**契約**。守門測試（`tests/Feature/ReadmeTest.php`）會把第一欄的識別字拿去跟實際的路由表、設定、指令、資料表對照 —— 表格寫錯或程式改了沒回來改表,測試就會紅。

### 端點

全部是 `POST`。路徑省略前綴（`admin` 與 `api` 分別來自 `matrix.admin-api-prefix` 與 `matrix.api-prefix`）。

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

「授權」= 登入 + 該選單節點的權限。`user` / `group` 的 `export`、`copy`、`sort` 端點存在但**套件出貨的選單沒有對應節點**,所以預設對所有人 403 —— 那三個動作在套件自己的兩個 controller 上是關閉的。

### 設定鍵

`config/matrix.php`。改任何一個都要記得 `mergeConfigFrom` 只合併頂層 key。

| 鍵 | 出貨值 | 說明 |
|---|---|---|
| `matrix.admin-api-prefix` | `'admin'` | 後台路由前綴 |
| `matrix.admin-menus` | `'base'` | 要載入哪些選單 bundle,空白分隔,排前面的覆蓋排後面的 |
| `matrix.api-prefix` | `'api'` | 前台路由前綴 |
| `matrix.file-private-disk` | `'local'` | 非公開檔案的 disk |
| `matrix.file-public-disk` | `'public'` | 公開檔案的 disk |
| `matrix.locales` | `'tw en'` | 允許的語系,對應 `resources/i18n/{語系}/` |
| `matrix.member-model` | `Member::class` | 會員 model,宿主可換成自己的 |
| `matrix.messaging` | 見範本 | queue 名稱與 channel 註冊。**巢狀 key,宣告就整份取代** |
| `matrix.packages` | `'app base'` | 資源疊層順序 |
| `matrix.resource-cfg` | `[]` | 資源後台開放編輯的 cfg bundle 白名單,**空 = 全部不開放** |
| `matrix.resource-i18n` | `[]` | 同上,一般翻譯 |
| `matrix.resource-i18n-menu` | `[]` | 同上,選單標題 |
| `matrix.resource-i18n-model` | `[]` | 同上,欄位標題 |
| `matrix.resource-i18n-options` | `[]` | 同上,下拉選項 |
| `matrix.resource-i18n-template` | `[]` | 同上,訊息樣板 |
| `matrix.vendor-model` | `Vendor::class` | 廠商 model |

白名單只擋非 ROOT。ROOT 不受它限制。

### 主控台指令

| 指令 | 作用 |
|---|---|
| `matrix:passwd` | 設定後台帳號密碼,建立管理員的唯一官方入口 |
| `matrix:prune-tokens` | 刪掉已經不能用來認證的 token,`--limit` 控制每批筆數（預設 1000） |
| `messages:dispatch` | 把到期的郵件 / 簡訊推進佇列 |

### 錯誤代碼

| 代碼 | 意思 |
|---|---|
| `actor-already-assigned` | 身分已設定，不可重複指派 |
| `data-conflicted` | 資料已被修改 |
| `data-not-found` | 查無資料 |
| `endpoint-not-found` | 端點不存在 |
| `file-too-large` | 檔案大小超過限制 |
| `invalid-captcha` | 驗證碼錯誤 |
| `invalid-cascade-relation` | 連動關聯必須是 hasOne、hasMany 或其 morph 形式 |
| `invalid-column-condition` | 欄位條件語法錯誤 |
| `invalid-column-expression` | 欄位運算式語法錯誤 |
| `invalid-identity-model` | 身分 model 設定錯誤 |
| `invalid-message-channel` | 訊息管道設定錯誤 |
| `invalid-message-content` | 訊息內容不得為空 |
| `invalid-message-driver` | 訊息傳送器設定錯誤 |
| `invalid-message-provider` | 訊息供應商設定錯誤 |
| `invalid-message-receiver` | 收件對象不得為空 |
| `invalid-mime-type` | 不接受這種檔案類型 |
| `invalid-parent-relation` | 上層關聯必須是 belongsTo |
| `invalid-password` | 密碼錯誤 |
| `invalid-resource-token` | 資源代碼格式錯誤 |
| `invalid-sort-order` | 排序內容與資料不符 |
| `invalid-token` | 登入憑證無效或已過期 |
| `invalid-username-or-password` | 帳號或密碼錯誤 |
| `message-provider-has-no-driver` | 訊息供應商未設定傳送器 |
| `message-refused-by-provider` | 訊息被供應商拒絕 |
| `message-template-not-found` | 查無訊息樣板 |
| `permission-denied` | 權限不足 |
| `request-failed` | 請求無法處理 |
| `server-error` | 系統發生錯誤 |
| `too-many-requests` | 請求過於頻繁，請稍後再試 |
| `undeclared-model` | Model 未宣告欄位 |
| `unknown-message-channel` | 訊息管道未註冊 |
| `unknown-package` | 套件未註冊 |
| `validation-failed` | 輸入資料有誤 |

### cfg 設定鍵

可以在資源後台線上編輯,也可以在自己的 `resources/cfg/{bundle}.php` 覆蓋。

| 鍵 | 出貨值 | 說明 |
|---|---|---|
| `admin.captcha-ttl` | `300` | 驗證碼有效秒數 |
| `admin.login-throttle-max` | `5` | 每個窗口容許的登入失敗次數 |
| `admin.login-throttle-window` | `1` | 節流窗口(分鐘) |
| `admin.password-pattern` | `'/^(?=.*\d)(?=.*[a-zA-Z]).{8,}$/'` | 後台密碼規則 |
| `admin.token-idle-minutes` | `30` | 後台 token 閒置多久失效 |
| `member.login-throttle-max` | `5` | 同上,前台會員 |
| `member.login-throttle-window` | `1` | 同上,前台會員 |
| `member.token-idle-minutes` | `30` | 同上,前台會員 |
| `vendor.login-throttle-max` | `5` | 同上,廠商 |
| `vendor.login-throttle-window` | `1` | 同上,廠商 |
| `vendor.token-idle-minutes` | `30` | 同上,廠商 |
| `file.max-size` | `0` | 上傳大小上限,**0 = 不限制** |
| `file.mime-patterns` | `''` | 型別白名單(正則,空白 = 不檢查) |
| `system.date-format` | `'Y-m-d'` | 日期顯示格式 |
| `system.datetime-format` | `'Y-m-d H:i:s'` | 日期時間顯示格式 |

### 資料表

| 資料表 | 內容 |
|---|---|
| `base_auth_token` | 各身分的登入 token |
| `base_city` | 縣市 |
| `base_city_area` | 行政區 |
| `base_file` | 上傳檔案(含去重雜湊與媒體資訊) |
| `base_group` | 後台群組 |
| `base_mail_log` | 郵件佇列與寄送結果 |
| `base_manipulation_log` | 稽核軌跡(誰改了哪一列的哪個欄位) |
| `base_member` | 前台會員 |
| `base_member_log` | 會員行為紀錄 |
| `base_menu` | 可線上維護的選單資料 |
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

清單回應含 `columns`（欄位描述）、`rows`、`pagination`、`actions`、`title`、麵包屑等。`columns[]` 每一項的兩個維度要分開讀:

- `type` 是**資料型別**（`text` / `integer` / `float` / `boolean` / `date` / `datetime` / `json`）—— 決定驗證與比較。
- `presentation` 是**呈現方式**（`plain` / `hidden` / `select` / `multiSelect` / `password` / `count`）—— 決定畫面怎麼畫。

一個「有選項的整數欄位」是 `type: integer` + `presentation: select`,兩個維度都完整。

**action 的 `url` 不含前綴。** 回應給的是 `widget/{id}/update` 這種相對路徑,前端要自己接上 `admin/`。

### 語系

送 `Matrix-Locale: en` header。值必須在 `matrix.locales` 裡,否則退回應用程式的預設語系。

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
| **上傳不檢查型別**（`cfg('file.mime-patterns')` 出貨空白 = 全部放行）**,而且沿用使用者送來的副檔名** | 要限制就設 `mime-patterns`（正則,空白分隔,**不能含逗號或分號** —— 那是分隔字元） |
| **上傳不限制大小**（`cfg('file.max-size')` 出貨 `0`）。真正的上限是 PHP 的 `upload_max_filesize` / `post_max_size` | 兩層都要調。只調 cfg 沒用,只調 ini 的話使用者會拿到 422 而不是 `file-too-large` |
| 檔案的 `privilege` 決定存哪個 disk（`0` 公開 / `1` 私有）。下載一律走 `admin/file/download`,**要登入** | 公開 disk 若做了 `storage:link`,那些檔案就有公開 URL —— 這是 disk 設定的結果,不是套件的存取控制 |
| **前台沒有任何檔案端點** | 前台要上傳就自己呼叫 `FileService::upload()`,並自己決定權限與限制 |
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
| **`base_mail_log` / `base_sms_log` 只能刪終端狀態**（成功 / 失敗） | `Scheduled` 是還沒送出的排程,刪掉等於取消一封信;`Sending` 可能是卡住的記錄,刪掉等於把問題藏起來 |
| **`base_file` 不要用時間清** | 刪列不刪磁碟檔會漏儲存空間,而去重讓一筆記錄可能被多處引用 —— 套件答不出「誰可以刪」 |
| **調大 `token-idle-minutes` 不會復活已經被清掉的 token** | 要調大就先調、再跑 prune |
| `token-idle-minutes` 的 `min:1` 驗證**只擋資源後台** | 自己在 `resources/cfg/admin.php` 寫 `0` 不受檢查,結果是全員登出、prune 清空整張表 |
| **訊息節流不是原子的** | 兩個 worker 同時檢查會同時通過。要修需要 `Cache::lock()`,而 `array` driver 做不到 |

### 行為細節

| 事實 | 說明 |
|---|---|
| **每個 `#[Action]` 都要有選單節點** | 漏一個,那個端點對所有人 403,包含 ROOT |
| **每一個 action 都跑在一個交易裡** | `BaseController::callAction()` 用 `DB::transaction()` 包住整個動作。要在 rollback 之後仍然執行的副作用（寄信、打第三方、刪檔）請註冊到 `RollbackCallbacks`,不要直接做 |
| **後台建立的帳號沒填「啟用時間」就登不進來** | 登入要求 `enable_time` 非 null 且已到,而表單沒有把它設成必填。症狀是「帳號或密碼錯誤」,看不出真正原因 |
| **樂觀鎖要自己呼叫** | `BaseModel::lock()` 會重讀該列並逐欄比對,值被別人改過就回 `data-conflicted`。CRUD 引擎不會自動幫你呼叫 |
| **`#[Action]` 會沿繼承鏈繼承** | 覆寫 action 不需要重新宣告 attribute |
| **編輯使用者一定要送 `password`** | 不送會被清空（更新是全量覆寫） |
| **權限樹只認四個動作** | `query` / `insert` / `update` / `delete`。選單節點上寫別的 `tag`（例如 `system`）不會變成可勾選的權限項目 |
| **手寫進資料庫的權限,形狀錯了會被靜默丟掉** | 存進去的形狀必須是 `{"路徑": {"動作": true}}`。值不是 true 的項目在下一次寫入時就消失,而且不會有任何錯誤 |
| **jsonb 欄位會宣稱自己可排序** | 引擎沒有把 Json 型別排除在排序之外。對它排序不會壞,但結果沒有意義 |
| **權限的寫入是「範圍內修訂」** | 編輯者只能授出自己有的權限,也洗不掉自己碰不到的;白名單以外的權限（例如維運用 SQL 寫的）會被保留 |
| **`guard` 是疊加的,套件先、宿主後** | 宿主看到的是已經過濾的值,而且無法覆蓋套件自己的 guard（例如「不能刪自己」） |
| **複製會沿用來源的 `ranking`** | 除非那個資源開了 `$sortable`,否則不會自我修復 |
| 複製時 `$generators` 管的欄位（建立時間、建立者等）**重新產生**,不照抄 | —— |
| **級聯複製只接受 `hasOne` / `hasMany` 及其 morph 形式** | `belongsToMany` 不支援 |
| **`get` / `update` / `delete` 會自動加上父層條件** | 巢狀資源不會誤動別人家的資料 |
| **匯出明寫 `'id'` 匯不出主鍵** | 要主鍵請寫 `'key=id'` |
| **`$exports = []` 是「沒有欄位」,不是「退回清單欄位」** | —— |
| **`$hidden` 只對 root model 的欄位有效** | join 進來的別名不受它保護 |
| **匯出回應的 `columns[]` 不含 `op` / `sortable` / `options`** | 前端要知道能篩什麼,必須先呼叫清單端點 |
| 麵包屑的 `label` 在多層資源下可能與直覺不同,`title` 三層都正確 | 照舊版行為,已用測試釘住 |
| **`context.{父層}_id` 是字串**,而 `rows` 裡的 id 是整數 | 前端比對時要注意型別 |
| **`getMenuNodes()` 的每個節點一定有 `group` 與 `tag`**（可能是 `false` / `null`） | 舊版是沒有就不輸出。用 `empty()` 判斷不受影響 |
| **`base_menu` 的迴圈與孤兒節點會被靜默丟掉** | 資料完整性由你負責,不會有錯誤訊息 |
| **`base_city_area.ranking` 與 `base_ranking` 序列不同量級** | 後台第一次拖曳排序就會把整組重編 |
| **`Operator` 掛在檢視表上** | 唯讀,不要對它 `save()` |
| **`AdminPermission` 在信封範圍外解析會靜默失敗** | 在 web 路由、console、queue job 裡解析它,`ServiceException` 不會被回報 |
| **對套件的 model 下批次 `delete()` / `update()` 會靜默失去稽核** | query builder 的批次操作不觸發 model 事件。要稽核就取出實例逐筆處理 |
| **稽核紀錄的 `after` 是 accessor 之後的值** | 你掛在可追蹤欄位上的 accessor 從此決定稽核內容 |

### 前台身分（member / vendor）

套件只提供零件,**整個流程歸你**:

| 事實 | 說明 |
|---|---|
| **沒有登入端點** | 自己用 `AuthToken::issue()` + `IdentityToken::attach()` + `login-throttle-api:{bundle}` 組 |
| **你的 member / vendor 資料表必須用 `primaryKey()`** | 用 `$table->id()` 會拿到 id = 1 的第一筆 —— 而 id 1 是 ROOT,稽核歸屬會靜默錯亂 |
| 那兩張表也必須帶 `auditings()` 四個欄位 | 稽核軌跡與建立者推導都靠它們 |
| 登出的語義、驗證碼生命週期、密碼規則 | 全部由你決定 |

### 自訂訊息通道

| 事實 | 說明 |
|---|---|
| 註冊點是 `config/matrix.php` 的 `messaging.channels`,**但那是巢狀 key** | 宣告 `messaging` 會整個取代掉內建的 mail / sms |
| 每個供應商還要一份 `resources/cfg/{provider}.php` | 裡面放 `driver` / 連線設定 / `interval` / `sandbox` |
| **供應商與樣板名稱要跨 channel 唯一** | —— |
| 樣板必須指名 `provider` | 否則 `send()` 當場回 `invalid-message-provider` |
| `messages:dispatch` 需要排程器,派送需要 queue worker | queue connection 設成 `sync` 的話節流的延遲會失效 |

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
