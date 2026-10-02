# ShareToku（シェアトク）

**紹介特典を、もっと簡単にシェアする。**

ShareToku は、紹介コード・紹介URL・紹介特典を一元管理し、自分のWebサイトやWordPressへ配信できる紹介特典SaaSです。

本番配信先URL（一般公開は未承認）：<https://share-toku.shikode.com>

紹介プログラムを利用する人は、自分が保有している紹介コードや紹介URLを登録できます。  
WordPressプラグインを利用すると、登録した紹介特典をブログやWebサイトの記事内へ簡単に表示できます。

## コンセプト

紹介コードは、サービスごとに管理場所や条件がバラバラです。

- どのサービスの紹介コードを持っているかわからない
- 特典内容が変更される
- 有効期限がある
- ブログの複数記事に同じ情報を書いている
- 修正するたびに過去記事を書き換える必要がある

ShareToku では、紹介特典の情報をSaaS側に一元化します。

```text
紹介特典を登録
      ↓
   ShareToku
      ↓
 ┌────┴─────┐
公開ポータル   REST API
               ↓
          WordPress
```

ShareToku上で情報を更新すれば、接続されたWebサイトにも更新内容を配信できます。公開ポータルは構想段階で、この実装には含まれません。

## FREEプランの基本モデル

FREEユーザーのWordPressでは、サイト所有者本人の紹介特典を主役として表示します。

さらに、その紹介特典と関連性の高い**別サービスのShareToku運営者特典**を追加表示する場合があります。

原則として、

- サイト所有者の紹介特典を優先する
- 同一サービスで所有者のコードを運営者コードへ差し替えない
- 運営者特典は別サービスとして表示する
- 関連性のない特典は表示しない
- 誰の紹介特典なのか明示する
- 掲載条件に同意したサイトにのみ表示する

ことを基本ルールとします。

PROプランでは運営者枠を非表示にします。決済連携は未実装で、プラン変更は管理者が行います。

## 主な機能

### 紹介特典管理

- サービス名
- 紹介コード
- 紹介URL
- 紹介された人の特典
- 紹介者の特典
- 適用条件
- 有効期限
- 最終確認日
- 公開・非公開状態

### 公開ポータル

将来、ShareToku上でも公開可能な紹介特典を検索できるようにする構想です。公開ポータルは未実装です。

想定する検索軸：

- サービス
- カテゴリ
- 特典内容
- キーワード

### WordPress連携

専用プラグインからShareTokuへ接続します。

```text
[sharetoku offer="OFFER_PUBLIC_ID"]
```

記事内に紹介特典カードを表示します。Gutenbergの動的ブロックにも対応します。

### 関連特典配信

FREEプランでは、本人の紹介特典に関連するShareToku運営者の別サービス特典を選定します。

初期版ではAIではなく、説明可能なルールベースでマッチングします。

## システム構成

### ShareToku SaaS

実装：

- Laravel
- MySQL
- REST API

担当：

- ユーザー管理
- ワークスペース管理
- 紹介特典管理
- サービスマスタ
- 公開審査
- 契約プラン
- 関連特典選定
- 配信API
- 分析

### WordPress Plugin

担当：

- ShareTokuとのサイト接続
- 特典選択
- ショートコード
- Gutenbergブロック
- APIレスポンスのキャッシュ
- 紹介カード表示
- FREE / PRO表示制御

WordPress側には紹介特典の正本を持たず、ShareTokuをSingle Source of Truthとします。

## API

公開ポータル API は未実装です。会員向け操作はセッション認証の `/workspaces/{workspace}` 以下と `/master`、`/reviews`、`/admin` に配置しています。

### WordPress Distribution API

```text
POST /api/v1/site-connections/exchange
GET  /api/v1/site/me
GET  /api/v1/site/offers
POST /api/v1/placements/resolve
POST /api/v1/events/batch
```

`placements/resolve` がWordPress配信の中心APIです。

## 権限モデル

```text
User
 ↓
Workspace
 ↓
Site
 ↓
Referral Offer
```

想定ロール：

- Visitor
- Member
- Site Editor
- Reviewer
- Administrator

ユーザーは自分のWorkspaceに属するデータのみ変更できます。

## 紹介制度の掲載管理

紹介制度によっては、不特定多数への紹介コード公開や第三者サイトへの掲載が禁止されている場合があります。

そのため、サービス単位で掲載ポリシーを管理します。

```text
approved
needs_review
restricted
prohibited
suspended
```

単に「紹介コードを登録できる」だけではなく、**ShareToku上で公開可能か、外部サイトへ再配信可能か**を管理します。

## 収益モデル

### FREE

- 紹介コード管理
- WordPress連携
- 基本的な分析
- サイト所有者の紹介特典を表示
- 関連するShareToku運営者特典を追加表示する場合あり

### PRO

予定：

- 運営者特典非表示
- 複数サイト
- 詳細分析
- デザインカスタマイズ
- API利用拡張

### 収益源

1. PRO等のSaaS利用料金
2. FREEサイトに掲載された運営者紹介特典から発生する紹介インセンティブ

紹介報酬自体をShareTokuがユーザー間で分配する仕組みはMVPには含めません。

## 設計原則

1. **紹介コード所有者を主役にする**
2. **所有者の紹介コードを勝手に他人のコードへ差し替えない**
3. **運営者特典は関連する別サービスとして明示的に追加する**
4. **関連性がなければ広告を出さない**
5. **紹介制度の規約を確認してから公開する**
6. **紹介情報の正本はShareTokuに一元化する**
7. **外部サイトにはAPIで安全に配信する**
8. **FREEの運営者枠は利用者の明示的な同意を前提とする**

## MVP

最初のゴールは、

> ユーザーが紹介特典を登録し、WordPressの記事内に「本人の紹介特典＋関連する運営者の別サービス特典」を安全に表示できること。

です。

MVPでは以下を実装します。

- ユーザー登録・ログイン
- Workspace
- サービスマスタ
- 紹介特典CRUD
- 公開審査
- サイト登録
- FREE / PRO権限制御
- 関連カテゴリ
- 関連特典選定
- WordPress配信API
- WordPressプラグイン
- ショートコード
- APIキャッシュ
- PR表示
- 基本的なクリック計測
- 管理者による配信停止

## Status

🚧 **MVP 実装中。一般公開は未承認です。** 本番配信済みですが、公開ポータルと公開前検証は未完了です。Issue・PRとソースの対応は [実装対応表](docs/implementation-status.md) を参照してください。 公開判定の残作業は [release checklist](docs/release-checklist.md) を参照してください。

## ローカル起動

PHP 8.3 以上（intl 拡張を含む）、Composer 2、MySQL 8 以上を用意します。MySQL に `sharetoku` データベースと専用ユーザーを作成し、接続情報を `.env` に設定します。

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

`GET /health` は `{"status":"ok"}` を返します。ブラウザーで `/register` からユーザー登録すると個人 Workspace が作成され、`/dashboard` で紹介特典と Site を管理できます。最初の system admin は信頼できる運用者が `php artisan tinker` で対象 User の `is_system_admin` を明示的に設定してください。公開登録から system admin に昇格する経路はありません。

開発テストは SQLite in-memory を利用します。

```sh
php artisan test --compact --do-not-cache-result
vendor/bin/pint --test
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress
composer audit
```

WordPress プラグインは [`wordpress-plugin/sharetoku`](wordpress-plugin/sharetoku) にあります。`SHARETOKU_OPERATOR_WORKSPACE_PUBLIC_ID` は運営者 Workspace の public ID に設定します。配信全体を緊急停止するときは `SHARETOKU_OPERATOR_OFFERS_ENABLED=false` に設定してください。

詳しい設計、運用、セキュリティは [`docs/`](docs) を参照してください。

GitHub Actionsによる本番CDの設定は [本番デプロイ手順](docs/deployment.md) を参照してください。`main` のCI成功後、SSHで `https://share-toku.shikode.com` へ配信します。
