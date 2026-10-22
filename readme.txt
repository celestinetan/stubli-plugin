=== Stubli ===
Contributors: celestinetan
Tags: photos, photo-sharing, social, indieweb, webmention
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.19.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A kinder social network for everyone, on your own WordPress site. Share photos, follow friends, comment. No ads, no algorithm, no central server.

== Description ==

**Stubli is in beta.** Anyone is welcome to try it. The feedback button in the app goes straight to the people who make Stubli.

Stubli turns your WordPress site into a calm, familiar place for your photos and the people you like. If you know Instagram, you know where to tap. But everything lives on your own site, and Stubli sites talk to each other directly with RSS and Webmention.

**On your site**

* Photo posts and carousels of up to 10 photos or videos (videos up to a minute), each with its own description.
* Collections as folder tabs, captions with clickable links, and content notes that keep a photo softly blurred until someone chooses to see it.
* A journal (for everyone, or only you) and a guest book that people sign from their own Stubli.
* Your colour and reading mode (Automatic, Light, Paper, Dark). The look works with any theme.

**The Stubli app at yoursite/app**

* Add it to your phone's home screen. Post, read and reply from there.
* Home: new photos from the people you follow, newest first. It ends with "That's everything for now".
* Discover: sites that the people you follow share publicly. No ranking, no algorithm.
* What's new: comments and guest book entries waiting for your OK.
* A daily summary at a time you choose, only when something is new, as a phone notification or by email. Never one per post.
* Edit or delete your posts, bring your photos and videos from Instagram, and invite friends with links that make you follow each other.

**Kind by design**

* Comments, not likes, and no counts anywhere.
* Location and camera details are removed from photos and videos before they're shared.
* People you follow can comment straight away; friends of friends wait for your OK; everyone else is blocked. Blocking is quiet: they are never told.
* Take a break: pause comments and the guest book without deleting anything.
* At the end of the setup you choose whether to stay out of search engines and to ask AI crawlers to stay away (both suggested). You can change both later.
* No "Powered by" link: a small "Made with Stubli" link in your footer appears only if you turn it on.

== Installation ==

1. Your host must let you upload plugins (on WordPress.com, any paid plan) and let other websites reach yours. https://stubli.app/get-started/ lists hosts that work.
2. Install and activate Stubli.
3. Stubli's setup opens by itself: your name and photo, interests, colour, your people and your daily summary. When you finish, Stubli adds the free Webmention plugin from WordPress.org, which carries comments between sites.
4. On your phone, open yoursite/app and add it to your home screen.

== Frequently Asked Questions ==

= Is there a Stubli server? =

No. Your posts, comments and the list of people you follow stay on your own site. Following someone means your site reads their site's feed.

= Can I leave? =

Yes. Your photos and journal are ordinary WordPress posts. Uninstalling Stubli removes its own tables and settings but keeps your posts, photos, journal and guest book.

= Does Stubli work with my theme? =

Yes. Stubli brings its own look for your public pages and turns it on for new installs. You can switch it off under Settings → Stubli.

= Is this host Stubli-ready? =

Settings → Stubli has a check that tests everything Stubli needs in a few seconds.

== External services ==

Stubli connects to other websites only for the features below. Nothing is sent anywhere just because someone visits your site.

* **Sites you follow, comment on or invite.** Your site fetches their public feed and profile (address, name, photos) and sends comments by Webmention. These are other people's Stubli sites, chosen by you.
* **stubli.app feedback (only when you press Send).** The app's feedback form sends your note, and, if you ticked them, your site address, device type and screenshot to stubli.app, the project's own site, so the people who make Stubli can read it. Privacy: https://stubli.app/privacy/
* **stubli.app host check (only when you press Check).** The host check asks stubli.app to try reaching your site, to tell you whether other sites can reach it. stubli.app receives your site's address. If you press "Check and send the result", the result (PHP and WordPress versions and the checks' outcomes, no personal data) is sent as a host report. Privacy: https://stubli.app/privacy/
* **Videos you embed in a journal entry.** If you paste a YouTube or Vimeo link into a journal entry, visitors who open that entry load the video from YouTube (youtube-nocookie.com) or Vimeo. Nothing loads from them otherwise. YouTube: https://policies.google.com/privacy, Vimeo: https://vimeo.com/privacy
* **WordPress.org (once, when you finish the setup).** Stubli asks WordPress.org for the Webmention plugin and installs it, the same way Plugins → Add New does. You can also add it yourself.
* **Phone notifications (only if you turn them on).** The daily summary is sent through your phone browser's own push service (for example Apple, Google or Mozilla), encrypted end to end so the service can't read it.

== Screenshots ==

1. A profile with collections as folder tabs.
2. The app: Home feed from the people you follow.
3. The setup that opens after installing.
4. Posting photos and videos from the phone.

== Changelog ==

= 0.19.0 =
* Stubli switches on straight away and opens its setup. The Webmention plugin it needs for comments is added with one tap at the end of the setup, or from Settings → Stubli.

= 0.18.0 =
* The setup now asks before changing site-wide settings: search engines, AI crawlers and comments.
* The "Made with Stubli" footer link is off unless you turn it on.
* The "Stubli look" switch in Settings → Stubli now saves.
* New sites answer only at stubli/v1.

= 0.17.2 =
* Marked as beta. Developer notes updated for the stubli_ prefix.

= 0.17.0 =
* Internal names moved from og_ to stubli_; existing sites are migrated automatically. REST API at stubli/v1 (og/v1 still answers).

= 0.16.1 =
* Daily summary as a phone notification (Web Push), with email as an option.
* White home-screen icon; the app is named just "Stubli".

= 0.15.0 =
* Videos up to a minute, tap to play. Location removed in the app and again on the server. Instagram videos import too.

= 0.14.0 =
* Your posts in the app: edit captions, collections, content notes and descriptions; delete with Undo. Followers' feeds pick up edits and deletions.

= 0.13.0 =
* The setup that opens after installing: Hello, Interests, Look, Your people, Ready. Seven colours and four reading modes.

= 0.12.0 =
* Discover: sites the people you follow share publicly.

= 0.11.0 =
* What's new, Home feed, journal photos, feedback and the host check.

== Developer notes ==

* Internal prefix stubli_, PHP namespace Stubli, REST namespace stubli/v1. Sites that ran Stubli before 0.17 also answer at the older og/v1 so friends' sites that haven't updated can still reach them; calls to other sites try stubli/v1 first.
* Content is standard WordPress posts: image, gallery and status post formats; collections are categories; the guest book is a page. A video is attached to its cover photo (meta _stubli_video), so everything that expects photos keeps working.
* Feeds add Media RSS for each photo, plus stubli:contentNote and stubli:video in the urn:stubli:1 namespace.
* Tables: {prefix}stubli_follows and {prefix}stubli_items.
