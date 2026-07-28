=== X AIアシスタント ===
Contributors: healingyasu
Tags: x, twitter, social-media, automation
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress記事の初回公開時に、テンプレートを適用してXへ自動投稿します。

== 説明 ==

X AIアシスタントは、OAuth 1.0aのユーザー認証を使ってWordPressとX APIを連携します。

主な機能：

* 通常のWordPress記事が初めて公開されたタイミングを検知します。
* `{title}`（記事タイトル）と`{url}`（記事URL）の置換項目を利用できます。
* 二重投稿と同時実行を防ぎます。
* 成功・エラーの投稿ログを保存します。
* 確認画面付きの実投稿テストボタンを備えています。
* SodiumまたはOpenSSLでAPI認証情報を暗号化します。
* Git UpdaterとGitHubリリースによる更新に対応します。

== インストール ==

1. リリースZIPをインストールするか、リポジトリを`wp-content/plugins/x-ai-assistant`へ配置します。
2. プラグインを有効化します。
3. 「設定 → X AIアシスタント」を開きます。
4. 書き込み権限を持つX開発者アプリのAPIキー、APIシークレット、アクセストークン、アクセストークンシークレットを入力します。
5. 設定を保存し、「テスト投稿を送信」を実行します。

X APIのアカウントと利用プランでは、`POST /2/tweets`が許可されている必要があります。テスト投稿を実行すると、Xへ実際に公開されます。
認証情報を安全に保存するため、サーバーにはSodiumまたはOpenSSLが必要です。

== 変更履歴 ==

= 1.0.2 =
* Git Updaterの公式仕様に合わせ、GitHub Plugin URIを完全なURLへ修正しました。

= 1.0.1 =
* 管理画面、通知、説明文を日本語表記へ統一しました。

= 1.0.0 =
* 初版を公開しました。
