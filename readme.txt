=== Stubli ===
Contributors: celestinetan
Donate link: https://buymeacoffee.com/celestinetan
Tags: photos, photo-sharing, social, indieweb, webmention
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.52.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A kinder social network for everyone, on your own WordPress site. Share photos, follow friends, comment. No ads, no algorithm, no central server.

== Description ==

**Stubli is in beta.** Anyone is welcome to try it. The feedback button in the app goes straight to the people who make Stubli.

Stubli turns your WordPress site into a calm, familiar place for your photos and the people you like. If you know Instagram, you know where to tap. But everything lives on your own site, and Stubli sites talk to each other directly with RSS and Webmention.

**On your site**

* Photo posts and carousels of up to 20 photos or videos (videos up to a minute), with optional descriptions.
* Collections as folder tabs, captions with clickable links, and content notes that keep a photo softly blurred until someone chooses to see it.
* A journal (for everyone, or only you) and a guest book that people sign from their own Stubli.
* A Library: lend books, games, tools and more to people nearby. People who have a Stubli ask to borrow from their app; anyone else can ask with a private note.
* Your colour and reading mode (Light or Paper). The look works with any theme.

**The Stubli app at yoursite/app**

* Add it to your phone's home screen. Post, read and reply from there.
* Home: new photos from the people you follow, newest first. It ends with "That's everything for now".
* Discover: sites that the people you follow share publicly. No ranking, no algorithm.
* What's new: comments and guest book entries waiting for your OK, and notes from visitors.
* A daily summary at a time you choose, only when something is new, as a phone notification or by email. Never one per post.
* Letters: write to other Stublis like letters, not a chat. Letters from people you don't follow wait for your OK.
* Edit or delete your posts, bring your photos and videos from Instagram, and invite friends with links that make you follow each other.
* Yours to keep: download everything, your Library included, as one zip that opens offline, or move your whole Stubli to another WordPress site. The people who follow you come along.

**Kind by design**

* Friends only: tick friends among the people you follow and share some posts just with them. They're never on your public page.
* Comments, not likes, and no counts anywhere.
* Location details are removed from photos and videos before they're shared.
* Comments from people you follow, and the people they follow, show straight away. Everyone else waits for your OK. Blocking is quiet: they are never told.
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

= Can I leave, or take everything with me? =

Yes. In the app, You → Settings and more → Yours to keep makes one zip with your photos and videos as they are on your site, your captions, journal, comments, guest book and the people you follow, as pages that open offline in any browser plus a data file. You → Settings and more → Move to another site copies your photos, journal, guest book, people and settings to a new WordPress site (for example your own server); your old site then points to the new one, and your followers' sites switch over by themselves.

Your photos and journal are ordinary WordPress posts. Uninstalling Stubli removes its own tables and settings but keeps your posts, photos, journal and guest book.

= Does Stubli work with my theme? =

Yes. Stubli brings its own look for your public pages and turns it on for new installs. You can switch it off under Settings → Stubli.

= Is this host Stubli-ready? =

Settings → Stubli has a check that tests everything Stubli needs in a few seconds.

== Third-party code ==

The Library's barcode reader (assets/app/isbn-scan.min.js) is built from parts of zxing-js/library (https://github.com/zxing-js/library), Apache License 2.0; the licence is in assets/app/isbn-scan-LICENSE.txt and the entry point in assets/app/isbn-scan-source.ts.txt.

== External services ==

Stubli connects to other websites only for the features below. Nothing is sent anywhere just because someone visits your site.

* **Sites you follow, comment on or invite.** Your site fetches their public feed and profile (address, name, photos) and sends comments by Webmention. These are other people's Stubli sites, chosen by you.
* **stubli.app feedback (only when you press Send).** The app's feedback form sends your note, your device type (for example "iPhone, Safari") and your Stubli version, and, if you ticked them, your site address and a screenshot, to stubli.app, the project's own site, so the people who make Stubli can read it. Privacy: https://stubli.app/privacy/
* **stubli.app host check (only when you press Check).** The host check asks stubli.app to try reaching your site, to tell you whether other sites can reach it. stubli.app receives your site's address. If you press "Check and send the result to the Stubli team", the results, your site's address, your Stubli and PHP versions and the host name you typed are sent as a host report. Privacy: https://stubli.app/privacy/
* **Videos you embed in a journal entry.** If you paste a YouTube or Vimeo link into a journal entry, visitors who open that entry load the video from YouTube (youtube-nocookie.com) or Vimeo. Nothing loads from them otherwise. YouTube: https://policies.google.com/privacy, Vimeo: https://vimeo.com/privacy
* **WordPress.org (once, when you finish the setup).** Stubli asks WordPress.org for the Webmention plugin and installs it, the same way Plugins → Add New does. You can also add it yourself.
* **Friends (only when you tick someone as a friend).** Your site gives that person's Stubli site a private key, and their site checks it back with yours. With the key, their reader can show your friends-only posts, and their app can show the things in your Library you keep for friends only. Untick them and the key stops working.
* **Notes (only when you send one, or someone leaves one).** A note you write goes from your site to the other person's Stubli site, which fetches it back from yours to check it really came from you; it gets your site's address, name and photo with it. Visitors without a Stubli can leave you a note with their name and a way to reach them; it's stored only on your site, seen only by you, and deleted after 90 days. Suggested privacy-policy text is added under Settings → Privacy.
* **Asking to borrow (only when you ask, or answer).** When you tap Ask to borrow, your site tells the lender's Stubli site that a request is waiting, and their site fetches it from yours: which thing, your note, your name, site address and profile photo. Their answers (yes with their hand-over line, not now, it's back) travel back the same way. Nothing goes anywhere else.
* **Libraries of people you follow (only when you open one).** When you tap Library next to someone under People, your site asks their Stubli site for its Library (with their friend key, if they gave you one). Their site sees your site's address.
* **Moving your Stubli (only when you paste a move link).** Your new site downloads your posts, photos, videos, journal, guest book, Library, people and settings from your old site, using the move link you made there. The link works for 7 days and only for that.
* **Open Library (only when you add a book to your Library by its barcode or ISBN, or tap Pick a cover from Open Library).** Your site asks Open Library (openlibrary.org, run by the Internet Archive) for the book's title, author, year and cover, sending only the book's ISBN, or, to pick a cover, the title and author you typed. While you choose, your own browser shows the covers straight from covers.openlibrary.org. Your site keeps a copy of the cover you pick in your media, so visitors load it from your site, never from Open Library. Privacy: https://archive.org/about/terms.php
* **Phone notifications (only if you turn them on).** The daily summary is sent through your phone browser's own push service (for example Apple, Google or Mozilla), encrypted end to end so the service can't read it.

== Screenshots ==

1. One account, many grids. Sort your photos into collections; each one is a grid of its own.
2. No company in the middle. Each Stubli is its own WordPress site, and sites talk to each other directly.
3. A feed that ends. Newest first from the people you follow, with no algorithm, likes or counts.
4. You decide who's in. Friends-only posts, and comments from anyone outside the people you follow (and the people they follow) wait for your OK.
5. Yours to keep. Download everything, or move your whole Stubli to another site; the people who follow you come along.

== Source code ==

Everything in this plugin is the readable source: the PHP, the app (assets/app/app.js and app.css) and the look (look/assets) are written by hand and shipped as they are. Nothing is compiled, bundled or minified, and there is no build step. The public repository, with the full history, is https://github.com/celestinetan/stubli-plugin

== Changelog ==

= 0.52.5 =
* Safety: the "can stubli.app reach your site?" check is now limited per visitor, so one visitor can no longer use it up for everyone.

= 0.52.4 =
* From your Stubli: when you follow, write to or comment on another Stubli, the screen names that Stubli by its address instead of saying "they".

= 0.52.3 =
* Behind the scenes: this list now shows the newest changes only, so WordPress.org shows it whole. Every older version is in changelog.txt, in the plugin.

= 0.52.2 =
* Guest book: one form to sign it or, with Private ticked, to write privately. A way to reach you is now optional.
* Letters: delete a whole conversation, and reply to people without a Stubli by email, text or call.

= 0.52.1 =
* Yours to keep: the card in Settings and more now says your Library comes along too, as it has since 0.52.0.

= 0.52.0 =
* Yours to keep: Download everything and Move to another site now bring your Library too: every book and thing, its cover and note, who has it, Now reading, and your pins and order.
* Safety: only the site's owner can act as your Stubli. A second account on the same site (an Editor or Author) can no longer follow, send letters or comment in your Stubli's name.
* Safety: moving a Stubli brings only real photos for your profile picture, cover and journal, and only Stubli's own details on comments.
* Safety: strangers can no longer use up the limits that keep spam out, so real comments, letters, borrow requests and invites keep getting through. Letters from people you don't follow wait quietly under the envelope instead of buzzing your phone.
* Safety: a comment always shows the site it really came from, even if its page claims to be someone else.
* Behind the scenes: Discover checks where another site sends it before following, like WordPress's own safe requests.

= 0.51.7 =
* Posting: Share works again when you already have 5 collections. Until now the button did nothing, without a message. Sorry about that.

= 0.51.6 =
* Behind the scenes: parking photos while they upload one at a time now uses the way WordPress.org asks plugins to handle files. Nothing changes in how posting looks or works.

= 0.51.5 =
* Posting: photos now go up one at a time, with "Uploading 7 of 20", so a big carousel no longer gets stuck on some hosts. If your site doesn't answer, the app now says so instead of waiting forever.

= 0.51.4 =
* Friends only: a friends-only post is never public, not even for a moment while its photos upload or while you move your Stubli. When someone stops being your friend, their Stubli lets go of the friends-only posts it kept.
* Privacy: the comments you write on other Stublis are no longer listed anywhere; each opens only at its own address. WordPress's public lists leave out photos that belong to nothing public and collections that hold only private posts.

= 0.51.3 =
* Friends only: when you block or stop following someone you had ticked as a friend, they stop seeing your friends-only posts straight away. When a friend's Stubli moves, the friendship moves with it. You can now untick someone you no longer follow.
* Letters: a letter is always shown as coming from the Stubli that sent it.

= 0.51.2 =
* Library: tidier tiles. Tap something to open a card with its note and the ways to borrow it: ask privately, or, easier, with a Stubli of your own.

= 0.51.1 =
* Favourites: Sites I follow is a card like your groups, with its title on its own line. On a computer, a card alone on the last row keeps its size instead of stretching. The card only shows once you follow someone.

= 0.51.0 =
* Journal: topics. Make up to five of your own (a short name or an emoji) under Your journal → Topics and pins, and pick one when you write an entry. They show as filters at the top of your Journal tab, like the Library's.
* Journal: pin up to three entries to the top of your Journal tab.

= 0.50.0 =
* Library: A to Z by title is the new standard order (The, A and An are skipped, so The Odyssey files under O). Newest first and your own order are still there, under Library → Change. A Library that was arranged by hand keeps its order.
* Library: the filters are icons; the one you pick shows its name.
* Journal: entries show as cards, like the photos in a collection, all on one page. Tap a card to read the entry.
* Library: visitors who have a Stubli of their own are told how to ask to borrow: follow, and ask from their app once they're followed back.

Older versions: see changelog.txt in the plugin, or https://github.com/celestinetan/stubli-plugin/blob/main/changelog.txt

== Developer notes ==

* Internal prefix stubli_, PHP namespace Stubli, REST namespace stubli/v1. Sites that ran Stubli before 0.17 also answer at the older og/v1 so friends' sites that haven't updated can still reach them; calls to other sites try stubli/v1 first.
* Content is standard WordPress posts: image, gallery and status post formats; collections are categories; the guest book is a page. A video is attached to its cover photo (meta _stubli_video), so everything that expects photos keeps working.
* Feeds add Media RSS for each photo, plus stubli:contentNote and stubli:video in the urn:stubli:1 namespace.
* Tables: {prefix}stubli_follows and {prefix}stubli_items.
