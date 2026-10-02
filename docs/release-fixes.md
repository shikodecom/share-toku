# 公開前の不具合修正（#23〜#25）

進行管理: [#29](https://github.com/shikodecom/share-toku/issues/29)。main `722241f` を基準に修正した。

- #23: 名前・ドメイン変更でsuspendedを維持する。Site更新・停止・code exchangeのSite行をロックし、停止と更新を直列化する。状態更新・Token/code失効・監査記録はトランザクション内で実行する。
- #24: Offer / ReferralProgramの更新時、requestにない期間項目は既存値を使い、更新後のstarts_at <= ends_atを検証する。明示NULLは解除として扱う。
- #25: WP disconnectの204 / 401 / 403でローカルToken・接続途中のstate・表示用接続情報・resolveキャッシュを削除する。API URLとSite public IDを保持して再接続可能にする。通信障害・5xxは失効済み状態と区別し、再試行を案内する。権限・nonce検証を維持する。

## 検証記録（2026-10-02 JST）

- SQLite: PHPUnit 52 tests / 382 assertions成功。
- MySQL 8.0.46（Unix socket、独立した一時DB）: SiteSuspensionTest / PeriodValidationTestの21 tests / 126 assertions成功。
- Pint / PHPStan成功。WordPress renderer / frontend / block checks成功。
- WordPress disconnectのmockテスト8シナリオ成功（204、401、403、500、通信障害、Tokenなし、権限なし、nonce不正）。CIへ追加。

実WordPressのcallback・切断/再接続・キャッシュ挙動は#26で確認する。この記録は実機E2E完了や一般公開承認を意味しない。
