=== X AIアシスタント ===
Contributors: healingyasu
Tags: x, twitter, social-media, automation
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

外部AI APIを使わず、WordPress記事の投稿文作成・予約・Xへの自動投稿を行います。

== 説明 ==

X AIアシスタントは、WordPress内で投稿文とハッシュタグを作成し、OAuth 1.0aのユーザー認証を使ってX APIへ投稿します。

主な機能：

* 記事単位でX投稿をON／OFFできます。
* 記事専用の投稿文、プレビュー、X投稿予約日時を設定できます。
* タイトル、抜粋、URL、タグ、カテゴリーを投稿文へ反映できます。
* タグとカテゴリーから最大5件のハッシュタグを作成します。
* 二重投稿と同時実行を防ぎます。
* 成功・エラー・予約登録のログを保存します。
* SodiumまたはOpenSSLでAPI認証情報を暗号化します。
* Git UpdaterとGitHubリリースによる更新に対応します。

投稿送信以外の外部APIは使用しません。

== インストール ==

1. リリースZIPをインストールするか、リポジトリを`wp-content/plugins/x-ai-assistant`へ配置します。
2. プラグインを有効化します。
3. 「設定 → X AIアシスタント」を開きます。
4. X APIの認証情報と共通テンプレートを保存します。
5. 記事編集画面の「X AIアシスタント」パネルで投稿内容を確認します。

X APIのアカウントとクレジットでは、`POST /2/tweets`が許可されている必要があります。テスト投稿を実行すると、Xへ実際に公開されます。

== 変更履歴 ==

= 2.0.0 =
* 外部AI APIを使わない投稿文生成へ方針を変更しました。
* 記事の抜粋、タグ、カテゴリーを使える置換項目を追加しました。
* 記事単位の投稿ON／OFF、専用文面、プレビュー、予約日時を追加しました。
* WordPress CronによるX投稿予約を追加しました。

= 1.0.3 =
* 設定画面にX Developer Consoleへの認証情報取得リンクを追加しました。

= 1.0.2 =
* Git Updaterの公式仕様に合わせ、GitHub Plugin URIを完全なURLへ修正しました。

= 1.0.1 =
* 管理画面、通知、説明文を日本語表記へ統一しました。

= 1.0.0 =
* 初版を公開しました。
