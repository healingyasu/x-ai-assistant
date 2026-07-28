# X AIアシスタント

WordPress記事の初回公開時に、Xへ自動投稿するための専用プラグインです。

## バージョン1.0.3

- 下書き・予約投稿から公開へ変わったタイミングを検知
- 記事タイトルとパーマリンクを取得
- `{title}`（記事タイトル）と`{url}`（記事URL）を使った投稿テンプレート
- OAuth 1.0aのユーザー認証でX API v2の`POST /2/tweets`へ投稿
- 成功・エラーの投稿ログを保存
- 二重投稿と同時実行を防止
- SodiumまたはOpenSSLで認証情報を暗号化保存
- 確認画面付きの実投稿テスト
- Git Updater対応ヘッダーを搭載
- 管理画面と通知を日本語化
- 設定画面からX Developer Consoleを開ける認証情報取得リンク

## インストール

リリースZIPをダウンロードし、WordPress管理画面の「プラグイン → プラグインを追加 → プラグインのアップロード」からインストールします。または、このフォルダを`wp-content/plugins/x-ai-assistant`へ配置してください。

有効化後、「設定 → X AIアシスタント」を開いて認証情報を入力します。X開発者アプリには書き込み権限が必要で、アクセストークンは投稿先のXアカウントに対応している必要があります。

## Git Updater

メインのプラグインファイルには、Git Updaterが読み取る次の必須ヘッダーを設定しています。

```text
GitHub Plugin URI: https://github.com/healingyasu/x-ai-assistant
Primary Branch: main
Release Asset: true
```

タグをGitHubへ送信すると、GitHub Actionsが`x-ai-assistant.zip`を生成してリリースへ添付します。

## セキュリティ

- 認証情報をリポジトリへ保存しません。
- パスワード入力欄へ保存済みの値を再表示しません。
- 設定変更とテスト投稿には、管理者権限とWordPressのnonce検証が必要です。
- 入力値を無害化し、画面出力をエスケープします。
- Xへの通信にはWordPressの安全なHTTP APIを使い、リダイレクトを許可しません。
- WordPressの`AUTH_KEY`から生成した鍵で認証情報を暗号化します。

WordPressのソルトを変更した場合は、認証情報を再入力してください。SodiumとOpenSSLのどちらも利用できない環境では、認証情報を保存しません。

## 今回の対象外

AIによる投稿文生成、ハッシュタグ提案、投稿予約、関連ジャンル検索、フォロー・いいね候補、AI交流支援は、今後のバージョンで追加する予定です。
