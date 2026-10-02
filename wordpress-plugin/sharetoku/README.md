# ShareToku WordPress Plugin

WordPress 6.5 以上、PHP 8.1 以上。`wordpress-plugin/sharetoku` を `wp-content/plugins/sharetoku` に配置して有効化します。

設定画面で ShareToku URL と Site public ID を入力し、接続します。紹介特典は `[sharetoku offer="OFFER_PUBLIC_ID"]` または「ShareToku 紹介特典」ブロックから配置できます。

Site Token は WordPress サーバーの autoload 無効 option に保存します。権限を持つ管理者のみ接続設定を変更できます。

ブロックの開発: Node.js 24 以上で `npm ci`、`npm run build`。`src/` から `build/` を生成し、生成済みファイルもプラグインに含めます。`@wordpress/scripts` を使います。
