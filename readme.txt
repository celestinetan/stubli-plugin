=== Stubli ===
Contributors: celestinetan
Donate link: https://buymeacoffee.com/celestinetan
Tags: photos, photo-sharing, social, indieweb, webmention
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.27.2
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
* Yours to keep: download everything as one zip that opens offline, or move your whole Stubli to another WordPress site. The people who follow you come along.

**Kind by design**

* Friends only: tick friends among the people you follow and share some posts just with them. They're never on your public page.
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

= Can I leave, or take everything with me? =

Yes. In the app, You → Yours to keep makes one zip with your original photos and videos, captions, journal, comments, guest book and the people you follow, as pages that open offline in any browser plus a data file. You → Move to another site copies your whole Stubli to a new WordPress site (for example your own server); your old site then points to the new one, and your followers' sites switch over by themselves.

Your photos and journal are ordinary WordPress posts. Uninstalling Stubli removes its own tables and settings but keeps your posts, photos, journal and guest book.

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
* **Friends (only when you tick someone as a friend).** Your site gives that person's Stubli site a private key, and their site checks it back with yours. With the key, their reader can show your friends-only posts. Untick them and the key stops working.
* **Notes (only when you send one, or someone leaves one).** A note you write goes from your site to the other person's Stubli site, which fetches it back from yours to check it really came from you; it gets your site's address, name and photo with it. Visitors without a Stubli can leave you a note with their name and a way to reach them; it's stored only on your site, seen only by you, and deleted after 90 days. Suggested privacy-policy text is added under Settings → Privacy.
* **Moving your Stubli (only when you paste a move link).** Your new site downloads your posts, photos, videos, journal, guest book, people and settings from your old site, using the move link you made there. The link works for 7 days and only for that.
* **Phone notifications (only if you turn them on).** The daily summary is sent through your phone browser's own push service (for example Apple, Google or Mozilla), encrypted end to end so the service can't read it.

== Screenshots ==

1. A profile with collections as folder tabs.
2. The app: Home feed from the people you follow.
3. The setup that opens after installing.
4. Posting photos and videos from the phone.

== Changelog ==

= 0.27.2 =
* On wider screens, Latest shows your three newest posts.

= 0.27.1 =
* Cover photo: drag, zoom and turn it to choose which part shows.

= 0.27.0 =
* Cover photo: an optional wide picture across the top of your page, chosen under Edit page.

= 0.26.2 =
* Open the app is a filled button on your own page, easier to spot.

= 0.26.1 =
* Small words under the app's bottom bar icons.

= 0.26.0 =
* The app is ready for translation (WordPress language packs), with whole sentences instead of pieced-together bits.
* Friends only is called the same everywhere; clearer setup step for your people; a next step when Home is empty.
* Gold text is easier to read on light pages.

= 0.25.1 =
* New time-of-day greeting icons: a sun with coffee in the morning, the sun handing over to the moon in the evening, and a smiling moon at night.

= 0.25.0 =
* A new greeting picture: the little house through the day. Morning sun and open shutters, afternoon flowers, an evening glow, and one warm lit window at night.
* Buttons and the bottom bar answer every tap: a gentle press, and a soft pill behind the tab you're on.

= 0.24.1 =
* Small ? links in the app open the matching answer in Stubli's help.

= 0.24.0 =
* Edit page in the app now has your name, pronouns and your photo or smiley, so you can change them any time after setup.

= 0.23.3 =
* The built-in look is in step with the Stubli theme (stubli.app gets a visual "How Stubli works" page).

= 0.23.2 =
* "Made with Stubli" is opt-in again, as WordPress.org asks: setup asks once, and nothing shows unless you say yes.

= 0.23.1 =
* Setup now asks "Help friends find Stubli?": say yes and a small "Made with Stubli · Make your own" line appears below your photos and in your footer. It stays off unless you say yes, and you can change it in Settings.
* Visitors who want to follow you or comment but have no Stubli yet now get a clear way to make one.
* stubli.app: the top bar has a single "Make your Stubli" button.

= 0.23.0 =
* Every Stubli without a photo now has a smiley: the Stubli smile in one of six colours, with a hat, glasses, a moustache or flowers. New Stublis get a random one; pick another in setup, or add a photo any time.
* Fewer, clearer colours: Forest and Sage are gone (Moss covers them). If you had one of them, your Stubli now uses Moss.

= 0.22.11 =
* The Beta label beside the logo is a little larger and easier to read.

= 0.22.10 =
* Profile pages: no more small gap between the collection tabs and the panel below them.

= 0.22.9 =
* Built-in look: vintage Swiss poster illustrations for the pitch page, one for each default interest.

= 0.22.8 =
* Built-in look: pitch page house rules play one at a time as you scroll.

= 0.22.7 =
* Built-in look: updated styles for the Stubli project site's pitch page.

= 0.22.6 =
* Built-in look: shared styles for the Stubli project site's pitch page.

= 0.22.5 =
* The built-in look: a wide, designed layout for the Stubli project site's "Stubli in a minute" page.

= 0.22.4 =
* The built-in look: the logo sits level with the top bar, with the Beta label at its top right. The home page of the Stubli project site is titled "Stubli".

= 0.22.3 =
* A small "Beta" label beside the Stubli logo in the app.

= 0.22.2 =
* On wider screens, the Latest card is now exactly as tall as your profile card.

= 0.22.1 =
* The Journal tab opens again on hosts that don't refresh their page addresses after an update (such as WordPress.com).
* In the app, your page's tabs match your public page: Journal as a book icon, Guest book and Links in green.

= 0.22.0 =
* Cards about you: up to three short answers ("A perfect Sunday…") sit behind your profile card. Visitors swipe through them and can reply to each one.
* Notes: private replies. From people you follow they arrive straight away; everyone else waits under Requests in What's new. Visitors without a Stubli can leave a note with a way to reach them.
* You is now your own profile: your cards, Edit page, Share and Invite, your latest post, collections and photos. Everything else is under the menu.
* Discover shows one person at a time, with one of their cards.
* Friendlier empty journals and collections.

= 0.21.0 =
* Friends only: choose friends under You → Your people, and pick Everyone or Friends for each post.
* A small smiling sun or sleepy moon over the Alps by the greeting, for morning, afternoon, evening and night on your own clock. It stays still when your phone asks for less motion.
* Home recovers by itself: Try again after an error, your last feed while offline, and a fresh load when you're back online.
* Clearer What's new: names and titles show properly, initials when a photo is missing, and many comments from one site fold together.
* Easier to read and tap: readable placeholders, visible labels on journal photo fields, edges on quiet buttons, no small capitals.
* Smaller things: the greeting uses your page's name, invite links are created right on Home, Back returns where you came from, the journal says "Draft saved".

= 0.20.0 =
* Yours to keep: download everything as one zip that opens offline in any browser.
* Move to another site: copy your whole Stubli to a new WordPress site with a move link. Followers switch to the new address by themselves.

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
