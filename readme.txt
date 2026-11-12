=== Stubli ===
Contributors: celestinetan
Donate link: https://buymeacoffee.com/celestinetan
Tags: photos, photo-sharing, social, indieweb, webmention
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.51.7
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
* **Moving your Stubli (only when you paste a move link).** Your new site downloads your posts, photos, videos, journal, guest book, people and settings from your old site, using the move link you made there. The link works for 7 days and only for that.
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

= 0.49.0 =
* Library: Electronics, for cameras, projectors, speakers and the like, next to books, games, tools and other things.
* Library: choose the order. Newest first with up to three things pinned to the top, or put everything in your own order by dragging, the same way as posts in a collection. Library → Change, next to how many things you have.

= 0.48.0 =
* Library: ask to borrow. Open the Library of someone you follow (People → Library next to their name), tap something and tap Ask to borrow, with a note if you like. They answer Lend it or Not now in What's new. While you have it, it's in your Library under Borrowed, and your page says "borrowed from a neighbour", never who lent it. Tap I've given it back when you return it.
* Only people the lender follows back can ask. Visitors without a Stubli can still write privately to ask.

= 0.47.2 =
* Favourites: Arrange groups is now Edit groups, and it's there as soon as you have one group, so you can rename it.

= 0.47.1 =
* Library: give a book a cover when the barcode finds none. Take a photo of it, or search Open Library by title and tap the cover that looks like your copy.

= 0.47.0 =
* Library: lend games, tools and anything else, not just books. Tap Add something and pick what it is; books still fill in from Open Library, everything else gets a photo, a name and a note.
* Now reading: up to 3 books at the top of your Library, from your shelf or one you're just reading (a library book, an e-book). Tap Finished when you're done.
* Friends only: keep some things off your page. The people you tick as Friend see them in their own Stubli app.
* See what the people you follow lend: People → Library, next to their name.

= 0.46.0 =
* Library: lend your books to people nearby. In the app, open More → Library and add a Library tab to your page. Add books by scanning the barcode on the back, typing the number under it, or by hand; your Stubli fills in the title, author and cover from Open Library. Mark a book on loan (only you see who has it; visitors see "On loan") and tap It's back when it returns. Add a rough area and how you hand books over, so neighbours know you're near. Asking to borrow from another Stubli comes next.

= 0.45.24 =
* Collection tabs are simpler again: up to five, each with a name of up to five characters (an emoji counts as one), in one plain row beside Journal, Guest book and Favourites. On a phone each collection tab shows its first two characters, or its emoji, so everything fits. The overlapping tabs and tab colours from 0.45.23 are gone.
* Have more than five collections, or longer names? Nothing is lost: your first five are your tabs, posts in the others show in All, and a long name stays as the title above its photos. In the app, Your collections lets you choose your five and give each tab a short name.

= 0.45.23 =
* Collection tabs now stay on one neat row, on your page and in the app. When they don't all fit, the open one shows in full and the others tuck behind it like folders in a drawer. Press and slide along the row to see each name, and let go to open it; a quick tap opens a tab straight away, and on a computer hovering shows the name.
* Give a collection's tab a colour: in the app, open Your collections and pick one of six soft colours for its top edge, or none.

= 0.45.22 =
* When your collections don't fit in one row, on your page and in the app, they now stack like cards in a box: the rows behind peek out above the one in front, and a tab lifts as soon as you touch it. On your page, Journal, guest book and Favourites stay beside the open collection instead of floating in a row of their own.

= 0.45.21 =
* In the app, under You: when your collections don't fit in one row, they now stack in rows, so none hide behind Journal, arrange, guest book and Favourites. The one you have open sits nearest your photos.

= 0.45.20 =
* Renaming a collection: pressing Done now also saves a change you haven't saved yet, so nothing you typed is lost.
* Fewer green buttons at once in the app: while you rename a collection, Save is the only green button, and Invite is now as quiet as Edit page and Share.

= 0.45.19 =
* On a phone, when your collections don't fit in one row, their folder tabs now stack in rows instead of sliding out of sight behind Journal, Guest book and Favourites. The open folder always sits right above the photos. With room to spare, nothing changes.

= 0.45.18 =
* In the app, You now shows a tab for every collection, and each tab shows all its posts. Before, collections whose posts were older than your newest 24 were missing until you scrolled.
* Renaming a collection to the name of an empty one now works: the empty one, which never shows as a tab, is folded in.

= 0.45.17 =
* Stubli now comes in two looks, Light and Paper. Dark mode and Automatic are gone: pages that used them show Light. The Aa button on your page switches between Light and Paper.

= 0.45.16 =
* A new photo shape, Tall (2:3), next to Portrait, Square and Landscape. It's the shape most cameras take, so those photos keep their top and bottom. A collection that starts with a Tall post shows tall tiles. Each shape now shows its ratio underneath (4:5, 2:3, 1:1, 3:2).

= 0.45.15 =
* Visitors without a Stubli can sign your guest book with their name, a message and, if they like, where they're from. Each entry waits for your OK in What's new. You can turn this off in Settings → Stubli.
* Visitors can also write to you privately: from the guest book (Write privately) or the envelope next to Follow on your page. Only you see these notes, in Letters.
* A post can now hold up to 20 photos (it was 10). Each video also sends a small cover picture, and most hosts take 20 files at once, so if a post with videos would go past that, the app tells you before sending instead of losing any.

= 0.45.14 =
* Move to another site now brings your cover photo and the people you ticked as friends, so you don't have to set them again on the new site.

= 0.45.13 =
* On a computer, your Favourites page looks balanced: Sites I follow is a tile like your favourite groups, and each row of tiles fills the width, so one group no longer sits alone in a third of the page.

= 0.45.12 =
* On a phone, when you've posted more than once this week, the Latest card says "and 3 more this week". Tapping it opens a This week panel with those posts, newest first.
* Fixed: a photo with a content note showed unblurred in the Latest card.

= 0.45.11 =
* Fixed: sharing a photo without a caption failed with "Content, title, and excerpt are empty." A caption is optional again, and you can take one off a post.
* Fixed: a long web address in a journal entry made the page wider than a phone screen.
* Fixed: under a post, the Next or Previous card could stick out past the edge of a small phone when the other post's title was long.
* Fixed: when a page's name has an apostrophe, like Zoë's Café, empty pages now say "Zoë is still choosing the good ones" instead of "Zoë's is…".
* Fixed: a favourites group name longer than 30 characters could stop favourites from saving.

= 0.45.10 =
* Change or delete your own comments on your posts: Edit and Delete sit under each one on the post's Comments screen in the app. If it was a reply that went to someone's Stubli, the change reaches theirs too. Deleted comments go to the WordPress Trash for 30 days.
* At the end of setup, an optional box (unticked) lets you say hello to Stubli's maker. If you tick it, your Stubli sends your site's address to stubli.app once. Nothing else is sent.

= 0.45.9 =
* Reply to comments on your posts and guest book: in the app, What's new → On your page → Reply. Your reply shows under their comment on your page and goes to their Stubli, which shows it in their What's new.
* Add a comment to your own post, even before anyone else has: the Comments screen in the app (from Edit post, or Add a comment under the post on your page) lists a post's comments with Reply under each.

= 0.45.8 =
* Fixed: moving your Stubli to a site that can't take videos (like WordPress.com's Personal plan) left out every post with a video. Those posts now arrive with their cover picture, and the app says how many videos stayed behind.

= 0.45.7 =
* Until your Stubli setup is done, a button at the top of your dashboard opens it. On WordPress.com the setup couldn't open by itself after installing.
* Home greets you by your first name. Without one, it uses your page's name only when that looks like a person's ("Ana Keller" or "Mia's Stubli"), and otherwise just says good morning.

= 0.45.6 =
* Fixed: a site name with an apostrophe or & (like "Mia's Stubli") showed odd codes instead of those characters in the setup and the app.
* Fixed: on a brand-new site, WordPress's sample "Hello world!" post made an empty "All" tab. Tabs now only count posts with a photo.

= 0.45.5 =
* Fixed: on a phone, a long title in the Latest card made the top of the page wider than the screen, so the profile card was cut off on the right.

= 0.45.4 =
* Under a post there's now Previous as well as Next, so you can go both ways through a collection.

= 0.45.3 =
* Favourites look tidier: each group is a tile of its own, side by side on a computer and one under another on a phone.

= 0.45.2 =
* A favourite's note can now be up to 140 characters.
* Fixed: in the app, a favourite with a long note pushed the row past the edge of the card.

= 0.45.1 =
* Fixed: an & or quotes in a collection's line under its tab showed as &amp; or &quot; in the app.

= 0.45.0 =
* Links is now Favourites, with a heart: up to 100 favourite places, shops and websites, each with an optional note. Sort them into up to 12 groups, like Coffee or Books, and they show as headings on your page. A link from Google Maps or Apple Maps shows with a map pin. Your links carry over by themselves.

= 0.44.1 =
* A collection on your page now shows all its photos at once, with no cut-off after 24 (pictures still load as you scroll). The Journal says More entries instead of Older entries, and Home says More posts.

= 0.44.0 =
* A photo without a collection now shows in All: an All tab appears first on your page only when you have such photos, and shows every photo, newest first. The old "Photos" folder is gone. New post offers None and starts on the collection you used last.

= 0.43.3 =
* Make your Stubli looks like a step-by-step wizard: one card per step, Step 2 of 4 with numbered stops you can tap to go back, and the details folded under More about this step.

= 0.43.2 =
* Make your Stubli opens on its first real question, picks up where you stopped when you come back, and uses the same four step names as the welcome page.

= 0.43.1 =
* On WordPress.com: posts and journal entries made in Stubli are no longer emailed to the site's subscribers or shared to its social accounts. Your followers get them in their own Stubli. Posts written in WordPress's own editor are left alone.

= 0.43.0 =
* Invites travel: open a friend's invite, make your Stubli on stubli.app, and the setup follows your friend for you at "Your people". No link to copy or paste.
* Setup: the message after using an invite link stays on screen.

= 0.42.2 =
* A new Stubli that has no journal entries yet shows its photos again. Since 0.41.0, such a site's page could look empty.

= 0.42.1 =
* Setup on WordPress.com: your smiley shows instead of WordPress.com's logo, and the home page always shows your Stubli, even if the site had a fixed home page.
* Setup: the interests you tick stay in place instead of jumping to the front; people you follow show their picture; the home-screen tip no longer guesses Android on a computer.

= 0.42.0 =
* Collections: Arrange by hand. Open a collection under Arrange your page, choose By hand, and drag the tiles into your own order (or tap one and move it with the buttons). Your page follows that order, new posts appear first, and the dates stay. Newest first is one tap away.
* Hold a journal entry's tile on the You screen to pin or edit it, like a photo.

= 0.41.1 =
* Collections: each collection shows only its own photos again. In 0.41.0, every collection showed every photo.

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
