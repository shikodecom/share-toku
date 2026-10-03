# 公開文書と問い合わせの準備（#27）

一般公開は未承認。Google OAuthはテストモードを維持する。運営者名・問い合わせ先・運営者が確認した文書が揃ってから、公開URLと画面導線を実装する。

## 確定が必要な情報

| 項目 | 現状 | 次の作業 |
| --- | --- | --- |
| 公開する運営者名 | 未提供 | 利用規約・privacy・問い合わせ表示に用いる正式表記を決める |
| 問い合わせ・通報先 | 未提供 | メールまたはフォームURLと対応担当を決める。通報にはOffer public ID / 公開URLを含められるようにする |
| 文書の確認担当・適用日 | 未確定 | 規約・privacy・掲載ガイドライン・公開審査方針を運営者が確認する |
| ログの保存期間・backup保管先 | 本番はsingleログ、raw analytics 90日。backupは初期構築ファイルのみ確認 | 日次backup・ログrotation・保持期間・失敗通知を#28で実証し、privacy文書と一致させる |
| Googleの公開設定 | テストモード | 必要なブランディング・ドメイン・policy URLと最新の公式要件を確認する。#15の公開承認後に設定を変更する |

未確定の連絡先や仮の規約を公開画面に載せない。

## ソースと一致させる説明

| 文書・画面 | 反映する実装上の事実 |
| --- | --- |
| 利用規約・FREE同意 | FREEでは同意と適切な候補がある場合に運営者特典を併記。PROでは本人枠のみ。運営者枠にPR表示 |
| 掲載ガイドライン・審査方針 | Offerの審査、公開用policyと外部配信用policy、期間・停止・削除、公式条件の確認、紹介者にも利益が生じ得ること |
| privacy | Google ID・email・氏名の認証利用、Workspace情報、紹介設定、計測の用途と保存期間、問い合わせ対応 |
| 計測の説明 | event ID、時刻、Site/Placement/Offer、slot、任意のcontent key / page pathを保存。query / fragmentを除外。raw IP・ブラウザーをまたぐ識別子をanalytics DBへ保存しない。インフラのアクセスログは別に確認 |
| 保存期間 | raw analyticsは既定90日でprune。日次集計、監査、認証・紹介情報、アクセスログとbackupの保存期間は別に決定 |
| 公開プロフィール | 初期状態は非公開。公開用display name・bio・websiteを管理者が設定。Google内部プロフィールを自動公開しない |

公開URLを用意した後、login、ポータル、FREE同意画面から文書へ到達できることを実ブラウザーで検証する。Offer詳細から通報先へ対象を渡し、実際に運営者が受信・対応できることを確認する。フォームへToken・認可コード・秘密情報を含めない。

Googleテストモード解除後の未登録アカウントによるログイン結果まで#27に記録し、#15で最終判定する。文書の法的な確認は運営者が行う。

## Google公式要件の再確認（2026-10-03）

公開用homepageではサービスの機能を説明し、termsとprivacyへのリンクを用意する。privacyはhomepageと同じドメインで公開し、Google側のアプリ名・support email・URLと一致させる。運営者名・連絡先の確定と文書確認後、`/terms`、`/privacy`、`/guidelines`、対象Offerを識別できる問い合わせ導線を実装・検証する。[OAuth policies](https://developers.google.com/identity/protocols/oauth2/policies)、[brand verification](https://developers.google.com/identity/protocols/oauth2/production-readiness/brand-verification)。

appの公開状態、brandingの審査状態、要求scopeは別に記録する。External / Testingでも、basic identity scope（openid、email、profile）のみの場合はtest user allowlistの例外がある。未登録アカウントの成功だけでPublishedへ変更されたとは判定できない。公開時にアプリ名・ロゴを同意画面へ表示するためのbranding審査と、sensitive / restricted scopeの審査を混同しない。[OAuth app state overview](https://developers.google.com/identity/protocols/oauth2/production-readiness/overview)。

Google管理画面の状態は今回変更していない。実際のscope / audience / branding / redirect URIの確認と、#15承認後の公開設定・未登録アカウントによる実ログインを記録する。
