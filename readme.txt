=== Stubli ===
Contributors: celestinetan
Donate link: https://buymeacoffee.com/celestinetan
Tags: photos, photo-sharing, social, indieweb, webmention
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.41.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A kinder social network for everyone, on your own WordPress site. Share photos, follow friends, comment. No ads, no algorithm, no central server.

== Description ==

**Stubli is in beta.** Anyone is welcome to try it. The feedback button in the app goes straight to the people who make Stubli.

Stubli turns your WordPress site into a calm, familiar place for your photos and the people you like. If you know Instagram, you know where to tap. But everything lives on your own site, and Stubli sites talk to each other directly with RSS and Webmention.

**On your site**

* Photo posts and carousels of up to 10 photos or videos (videos up to a minute), with optional descriptions.
* Collections as folder tabs, captions with clickable links, and content notes that keep a photo softly blurred until someone chooses to see it.
* A journal (for everyone, or only you) and a guest book that people sign from their own Stubli.
* Your colour and reading mode (Automatic, Light, Paper, Dark). The look works with any theme.

**The Stubli app at yoursite/app**

* Add it to your phone's home screen. Post, read and reply from there.
* Home: new photos from the people you follow, newest first. It ends with "That's everything for now".
* Discover: sites that the people you follow share publicly. No ranking, no algorithm.
* What's new: comments and guest book entries waiting for your OK, and notes from visitors.
* A daily summary at a time you choose, only when something is new, as a phone notification or by email. Never one per post.
* Edit or delete your posts, bring your photos and videos from Instagram, and invite friends with links that make you follow each other.
* Yours to keep: download everything as one zip that opens offline, or move your whole Stubli to another WordPress site. The people who follow you come along.

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

== External services ==

Stubli connects to other websites only for the features below. Nothing is sent anywhere just because someone visits your site.

* **Sites you follow, comment on or invite.** Your site fetches their public feed and profile (address, name, photos) and sends comments by Webmention. These are other people's Stubli sites, chosen by you.
* **stubli.app feedback (only when you press Send).** The app's feedback form sends your note, your device type (for example "iPhone, Safari") and your Stubli version, and, if you ticked them, your site address and a screenshot, to stubli.app, the project's own site, so the people who make Stubli can read it. Privacy: https://stubli.app/privacy/
* **stubli.app host check (only when you press Check).** The host check asks stubli.app to try reaching your site, to tell you whether other sites can reach it. stubli.app receives your site's address. If you press "Check and send the result to the Stubli team", the results, your site's address, your Stubli and PHP versions and the host name you typed are sent as a host report. Privacy: https://stubli.app/privacy/
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

== Source code ==

Everything in this plugin is the readable source: the PHP, the app (assets/app/app.js and app.css) and the look (look/assets) are written by hand and shipped as they are. Nothing is compiled, bundled or minified, and there is no build step. The public repository, with the full history, is https://github.com/celestinetan/stubli-plugin

== Changelog ==

= 0.41.0 =
* Journal: an entry can also show in one of your collections, as a tile the same size as the photos. With a photo inside, the tile shows the first photo (or the words, if you prefer); without one, a paper tile with the title and first lines. A small page sign marks it. It stays in your Journal tab, and Only-me entries never show in a collection.

= 0.40.1 =
* Journal: a short journal shows All entries once, at the top; it appears again at the bottom only when there are older entries.

= 0.40.0 =
* Letters: notes between Stublis now have a proper inbox, one conversation per person, behind an envelope at the top of the app. Unread letters are marked, letters from people you don't follow wait for your OK, and your phone can tell you when a letter arrives.
* The New tab is now called Post.

= 0.39.3 =
* For the WordPress.org review: every piece of HTML Stubli prints now goes through WordPress's escaping, smiley avatars are small picture files in the plugin (older links forward to them), the offline archive's pages and styles come from template files, the notification script is a plain file, and the readme says where the source lives. Nothing changes in how Stubli looks or works.

= 0.39.2 =
* Fixed: after replacing a photo, some hosts (WordPress.com among them) could show an older, deleted photo instead of the new one. Every new photo now gets a file name of its own.

= 0.39.1 =
* Under the hood, for the WordPress.org review: the reading-mode script and colour styles now load through WordPress's own script and style system, database queries name their tables with WordPress's placeholder, and JSON output uses WordPress's standard escaping. Nothing changes in how Stubli looks or works.

= 0.39.0 =
* The demo in your browser now has Ana's made-up friends: their posts in Home, comments, guest book entries, a note and people to discover. Real Stublis are unchanged.

= 0.38.1 =
* Shared look: small fixes for stubli.app's own pages (no change on your Stubli).

= 0.38.0 =
* The demo in WordPress Playground now opens on a ready-made Stubli (Ana, made up, with drawings, a journal and a guest book) and says on every page and app screen that it's a demo, with a link to make your own. Real Stublis never show this.

= 0.37.1 =
* The app notices when your site has a newer Stubli (for example when it stayed open on your phone through an update) and offers "Stubli has been updated. Reload". Nothing reloads by itself.

= 0.37.0 =
* Journal titles: an entry can have a title of its own (optional; the date stays). The title shows above the entry, names its page, and moves with your Stubli.
* All entries: a list of every journal entry, by month, linked from the top and bottom of the Journal tab.
* The Journal tab now has Older entries and Newer entries links, so entries older than the newest ten can be reached.

= 0.36.1 =
* Full screen: the close and arrow buttons stay easy to see on light photos.

= 0.36.0 =
* Full screen for visitors: tap or click any photo in a post to see it as large as the screen allows, in full size. Turn a phone sideways and a landscape photo fills the screen. Swipe or use the arrow keys between the photos of a post; close with the cross, Esc, the Back button or a swipe down. Pinch to zoom works as usual. A photo behind a content note opens only after "Show photo".

= 0.35.1 =
* On bigger screens, a post whose cover photo is landscape now shows the photo across the full width of the page, with the caption and comments underneath, so wide photos are seen large.

= 0.35.0 =
* Edit post can now add, remove and replace photos, not only reorder them. A replaced photo keeps its place, a post keeps at least one photo, and new photos are cropped to the post's shape unless you pick another. Photos you take out are deleted from your site a few minutes after you save, so Undo can still bring them back. A post whose photos changed shows a quiet "Edited" next to its date.

= 0.34.0 =
* Little surprises on special days, redrawn: on New Year the letters of the Stubli logo dance under falling confetti; on Valentine's Day the smiley gets heart eyes; at Halloween a pumpkin sits behind it; at Christmas fairy lights drape over the letters; on 1 August a Swiss flag replaces the dot on the i (on Swiss Stublis only); and on your Stubli's birthday the i is a candle in a cupcake. Each moves for about five seconds when the page opens and again on hover, and stays still with reduced motion. Switch it off in the app under Colour and reading mode.

= 0.33.3 =
* Videos and hosts: if your host doesn't take video files (WordPress.com's Personal plan, for example, keeps them for Premium and above), the app now says so before you start, offers photos only, and the Instagram import brings your photos and tells you how many videos stayed behind. Before, a video failed only at Share, with a message that didn't name the reason.

= 0.33.2 =
* Little surprises: the smiling ü in the Stubli logo now dresses up instead, keeping its face: a Samichlaus hood (1 to 6 December), a party hat at New Year, bunny ears at Easter, and a candle on your Stubli's birthday.

= 0.33.1 =
* Little surprises are now simpler and quieter: on special days, the dots on the ü in the Stubli logo dress up (a mandarin and a peanut for Samichlaus, sparkles at New Year, little eggs at Easter, balloons on your Stubli's birthday). The drawings on the profile card from 0.33.0 are gone. Still on by default; switch it off in the app under Colour and reading mode.

= 0.33.0 =
* Little surprises on special days: Samichlaus (1 to 6 December), New Year, an Easter egg to tap, and your Stubli's birthday, a year after your first post. Visitors see each one once a day; a tap hides it, and with reduced motion they stay still. On for new and existing sites; switch them off in the app under Colour and reading mode, where you can also try each one.

= 0.32.0 =
* App: edit your guest book (welcome line, pin, remove with undo) and your links (add, change, reorder, remove) right from your You screen.
* Collections: give each one a short line (hold a tab, tap the pencil); it shows quietly at the top of the collection, on your page and in the app.
* Guest book: the welcome line is quieter.
* Your page, wider screens: Latest is a list again, filled with as many of your six newest posts as fit beside your profile card.

= 0.31.3 =
* Your page, wider screens: Latest shows the newest post and the five before it as small photos.
* Guest book and Links use the same panel, headings and cards as the rest of your page.

= 0.31.2 =
* Invite page: "How to get started" goes to the start of the welcome page, like every Make your Stubli button.

= 0.31.1 =
* "Your first day" on Home for a new Stubli: post a photo, invite a friend, add to home screen. Ticks itself off; hide any time.

= 0.31.0 =
* People tab (was Discover): invite a friend, follow by address, your people and friend ticks, then suggestions.
* You: hold a collection tab to reorder (drag) and rename; hold a photo to pin it. Settings and more is shorter.
* Journal drafts save themselves to your site; Publish when ready. Unsaved edits to a published entry are kept on the phone.
* Guest book: signed cards, a count, and Pin / Unpin for the owner.
* Colour and reading mode can be changed any time (Settings and more).
* Comments that didn't reach a site are sent again in the background (a week; a month during a break).
* "Still up?" greeting after midnight.
* Many help and wording fixes from an audit.

= 0.30.2 =
* App: the highlight behind the active tab sits exactly on its icon, clear of the label.

= 0.30.1 =
* Journal: underline and checklists (tap the circle to tick an item; "[] " starts one).

= 0.30.0 =
* Journal: simple formatting like the Notes app (headings, bold, italic, strikethrough, lists, quotes), photos placed right in the text.
* Journal: edit or delete any entry (You → Settings and more → Your journal). Edits keep the date and show an "Edited" time; delete can be undone.

= 0.29.2 =
* Rename a collection and change the line under its tab, any time (Arrange → Edit).
* Prompt cards: calmer question, your answer first, each card a deeper shade of your colour, no logo.
* Landscape crop is now 3:2, the same shape as landscape tiles on your page.
* Descriptions are fully optional: no more reminders.

= 0.29.1 =
* A collection whose first photo is landscape (or square) shows all its tiles that way.
* Fix: a cover photo no longer makes the page wider than a phone screen.

= 0.29.0 =
* Edit post: change the order of the photos and choose the cover.
* New post: descriptions are optional, behind Add descriptions.
* Cards about you look like party-game cards: bold colour, the question big.

= 0.28.3 =
* Cards about you: short answers are larger and centred, so every card looks full.

= 0.28.2 =
* Signed in, your page always shows your latest changes (no stale copy from the browser). A link to see your new cover on your page.

= 0.28.1 =
* A new cover photo always shows straight away (fresh address each time).

= 0.28.0 =
* Arrange your page: put your collection tabs in your own order, and pin up to three posts to the top of each collection.

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
