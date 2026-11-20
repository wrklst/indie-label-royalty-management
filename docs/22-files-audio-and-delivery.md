# Files, audio and delivery

This page covers the files kept with a release, covers and their thumbnails, audio masters and their MP3 playback copies, the audio player, and the three downloads for delivering a release: the metadata CSV, the full package and the Subvert ZIP.

## Who can see files

Files are private. Only signed-in admins of the label can open, play or download them. Payees cannot, and there are no public links. See [Team, roles and sign-in](40-team-roles-and-sign-in.md).

Files are stored on the server or in a storage bucket, depending on your setup; see [Configuration](03-configuration.md).

## Upload files to a release

1. Open the release and scroll to the panel **Files**.
2. Optionally choose a type in the field below the drop area. **Detect type** is the default.
3. Drop one or more files on the drop area, or click **browse** and select them.
4. Each file is uploaded as soon as it is added, one after the other. A finished file leaves the drop area and appears in the list; one that was refused stays, in red, with the reason.

With a storage bucket, files can go from your browser straight to the bucket instead of through the server; see [Connections](42-connections.md#direct-uploads).

Example: for `Example Release` you choose **As cover** and upload `cover.jpg`. Then you switch back to **Detect type** and upload `01 Opening.wav` and `02 Second Track.wav`. Both are recognised as audio and attached to track 1 and track 2.

### File types

| Type     | Used for                              | Chosen by **Detect type** for                                                         |
| -------- | ------------------------------------- | ------------------------------------------------------------------------------------- |
| Cover    | The one cover of the release.         | Never. Choose **As cover**, or change the type afterwards.                            |
| Audio    | Masters, one or more per track.       | Audio files, and files ending in `wav`, `flac`, `aif`, `aiff`, `mp3`, `m4a` or `ogg`. |
| Artwork  | Further images such as booklet pages. | All other images.                                                                     |
| Document | Texts and sheets.                     | Files ending in `pdf`, `doc`, `docx`, `txt`, `rtf`, `md`, `csv` or `xlsx`.            |
| Video    | Videos.                               | Video files.                                                                          |
| Other    | Anything else.                        | Everything not listed above.                                                          |

The type you choose in the field applies to every file of that upload.

### How audio finds its track

An audio file uploaded without a track is attached to a track of the release by its file name, in this order:

1. The file name contains the ISRC of a track, with or without dashes.
2. The file name starts with a track number, such as `02 Something.wav`, and exactly one track has that number.
3. The file name contains the title of a track. The longest matching title wins.

A file such as `bounce final v3.wav` matches nothing and stays without a track. Set the track by hand in the file table.

### The file table

| Column   | What it shows                                                                                                               |
| -------- | --------------------------------------------------------------------------------------------------------------------------- |
| File     | The original file name. Images and audio are links that open in a new tab. Audio also shows the state of its playback copy. |
| Type     | A field to change the type. The change is saved at once.                                                                    |
| Track    | For audio: a field to choose the track, or **No track**. Saved at once.                                                     |
| Size     | The file size.                                                                                                              |
| Uploaded | The upload date.                                                                                                            |

The arrow at the end of a row downloads the file exactly as it was uploaded. The bin deletes it after a confirmation.

### Size limit and refused files

- The limit is per file and is shown next to the upload button. The default is 1024 MB. Your server administrator can change it; see [Configuration](03-configuration.md).
- A file above the limit is skipped with a message, and the other files of the same upload continue.
- Files ending in `php`, `phtml`, `phar`, `html`, `htm`, `js`, `mjs`, `exe`, `sh`, `bat`, `cmd` or `svg` are refused with **Files of this type cannot be stored here.**

## Covers and thumbnails

- A cover must be a JPEG, PNG or WebP image.
- A release shows one cover. When you upload a new one, or change another file to the type **Cover**, the previous cover becomes **Artwork**. Nothing is deleted.
- The release page shows a reduced copy of the cover (at most 600 pixels on the longest edge). Click it to open the original.
- The release list and the player show a square thumbnail of 96 pixels, cut from the centre of the image.
- Reduced copies are JPEG files on a white background. They are made the first time they are needed and kept with the release files. The original is never changed.
- Until a cover exists, the release page shows **No cover yet. Upload one under Files.**

## Audio masters and playback copies

An uploaded master is kept exactly as it is. Downloads and delivery packages always contain the master.

For listening in the browser, the server makes an MP3 copy of every audio file with the program ffmpeg. With default settings this is a 320 kbit/s stereo MP3 at 44.1 kHz. If the file is attached to a track, the copy is tagged with the title, artist and release name. A file that already is an MP3 is played as it is and gets no copy.

The file table shows the state below each audio file name:

| State                    | Meaning                                                                                                |
| ------------------------ | ------------------------------------------------------------------------------------------------------ |
| Converting for playback… | The copy is waiting to be made or is being made. The table checks again every few seconds.             |
| MP3 playback copy ready  | The copy exists. The player uses it.                                                                   |
| Played as uploaded       | The file is an MP3 and needs no copy.                                                                  |
| Playback copy failed     | The conversion did not work. Hover over the text to read the reason. Click **Convert again** to retry. |
| No playback copy yet     | No conversion was started for this file. Click **Convert** to start one.                               |

When a copy is made and the track has no length yet, the length of the audio is filled in as the track's duration. An existing length is not changed.

### Without ffmpeg

If ffmpeg is not installed on the server, every conversion ends in **Playback copy failed**. You can still work:

- MP3 uploads play.
- Other masters, such as WAV or FLAC, are played directly from the master in browsers that support the format.
- Formats the browser cannot play stay silent in the player.

If files stay at **Converting for playback…** for a long time, the background worker that makes the copies is probably not running. Installing ffmpeg and running the worker are described in [Installation](02-installation.md) and [Configuration](03-configuration.md). Missing copies can be made later in one go; see [Command line](50-command-line.md) and [Troubleshooting](52-troubleshooting.md).

## The audio player

The player is a bar at the bottom of the window. It appears as soon as something is queued and disappears when the queue is empty. It is available to label admins only.

### Start playback

Every track with audio has two buttons, in the **Listen** column of the track list and of the release page, and at the top of the track page:

- **Play now** starts the track at once. It moves to the front of the queue; whatever was queued stays behind it.
- **Add to queue** adds the track to the end of the queue without interrupting what is playing.

For the track that is currently in the player, the first button pauses and resumes it, and **Playing** or **Paused** is shown next to it.

A whole release can be played from three places:

| Place                                         | Buttons                                                    |
| --------------------------------------------- | ---------------------------------------------------------- |
| The release page, above the track table       | **Play release** and **Queue release**                     |
| The release list, in each row                 | Icon buttons **Play release** and **Add release to queue** |
| An artist's page, in each row of **Releases** | The same icon buttons                                      |

**Play release** replaces the queue with the tracks of the release and starts the first one. **Queue release** adds them to the end. Only tracks with audio are included, in play order. The buttons are missing when no track of the release has audio.

A track is in the queue at most once. Adding it again shows a message that it is already queued.

### The player bar

From left to right:

- The cover, the track title (a link to the track page), and the artist and release (links to their pages).
- **Previous track**, **Play** or **Pause**, **Next track**. The previous button jumps back to the start of the track; press it within the first three seconds to go to the track before.
- The seek bar with the elapsed and the total time.
- **Mute** and the volume slider.
- The queue button, which shows the number of queued tracks.

On narrow screens the previous button and the volume controls are hidden.

Pressing the space bar starts and stops playback, unless the cursor is in a form field or on a button or link. The media keys of your keyboard work in browsers that support them.

### Edit the queue

Click the queue button to open the queue.

- Click a track to play it.
- Drag the handle on the left of a track to move it. With the keyboard, focus the handle and press the up or down arrow.
- Click the **X** on the right to remove a track.
- **Clear queue** empties the queue and closes the player.
- Press Escape or click the **X** at the top to close the queue.

Reordering never interrupts the track that is playing.

### Across pages and after a reload

- The player keeps playing while you move between pages of the application.
- The queue, the position in the current track, the volume and mute are remembered in your browser. After a reload, or when you come back later, the player is restored **paused** at the position you left. Press play to continue.
- The queue belongs to you and one label. It is dropped when you sign out or switch to another label.
- The queue is stored in the browser you used. It is not available in another browser or on another device.

### When a track does not play

The player shows a message in red instead of the artist line, for example **This track cannot be played** or **Playback copy is still being prepared**. If there is a next track, the player skips to it after a moment. In the queue, the track is marked **Could not be played**. Check the state of the file in the file table of the release.

When a track has several audio files, the player uses the first uploaded MP3, M4A or AAC file if there is one, otherwise the first file in a format browsers can play, otherwise the first upload.

## Metadata CSV

The metadata CSV is a spreadsheet with one line per track. A release without tracks gets one line.

- For all releases: open **Catalog → Releases** and click **Metadata CSV**. The file is named `release-metadata.csv`.
- For one release: open the release, click **Delivery** and choose **Metadata CSV**. The file is named after the catalog number, for example `ex001-metadata.csv`, or after the release name when there is no catalog number.

The columns are:

```text
catalog_number, upc, release_title, release_version, release_artist, release_type,
release_date, original_release_date, preorder_date, label, genre, subgenre, language,
explicit, p_line, c_line, status, blocked_services, blocked_territories, disc_number,
track_number, track_title, track_version, track_artist, featuring, remixers, isrc, iswc,
duration, duration_seconds, track_explicit, track_language, track_genre, bpm, key,
composers, lyricists, producers, publishers, track_p_line, preview_start_seconds
```

- `track_artist`, `track_explicit`, `track_language`, `track_genre` and `track_p_line` are filled from the release when the track has no value of its own.
- `featuring` and `remixers` come from the artist credits of the track. `producers` combines the **Producer** credits with the names in the **Producers** field.
- `duration` is written as minutes and seconds, for example `3:25`; `duration_seconds` as `205`.
- Text that a spreadsheet program would treat as a formula gets a leading apostrophe.

## The delivery package

**Delivery → Full package** downloads a ZIP archive with everything that belongs to the release. The archive and the folder inside it are named after the catalog number, artist and release name.

Example for `Example Release`:

```text
EX001 - Example Artist - Example Release/
  cover.jpg
  audio/01 - Example Artist - Opening.wav
  audio/02 - Example Artist - Second Track.wav
  artwork/booklet-page-1.png
  documents/contract.pdf
  metadata.csv
  release.txt
```

- `cover` is the current cover.
- `audio/` holds every audio file of the release. A file attached to a track is renamed to track number, artist, title and version. A file without a track keeps its upload name.
- `artwork/` holds the files of the type Artwork; `documents/` holds documents, videos and other files. Both keep their upload names.
- `metadata.csv` is the metadata CSV of this release.
- `release.txt` is a plain text sheet to copy from when a store only offers a web form: title, artist, label, catalog number, UPC / EAN, format, release date, genre, ℗ and © lines, blocked services and territories, the track list with lengths and ISRCs, the press release and the key facts.

The package always contains the original files, never playback copies or thumbnails. Two files that would get the same name are numbered: the second becomes `name (2)`.

## The Subvert ZIP

**Delivery → Subvert ZIP** downloads one ZIP in the form the upload form of Subvert accepts: the cover and one audio file per track, without folders.

```text
Example Release.zip
  cover.jpg
  01 Opening.wav
  02 Second Track.wav
```

- The name of the ZIP is the release name, with the version in brackets. Subvert uses it as the release title.
- Each track is sent with one file. A lossless file (WAV, AIFF or FLAC) is preferred over an MP3; among equals, the most recent upload is used. The format is judged by the file ending.
- Files are numbered by track number. If a track has no number, or two tracks of a disc share one, all tracks are numbered by their position instead.
- When the release has more than one disc, the disc number comes first: `1-01 Opening.wav`.
- Pricing, credits and rights are not part of the ZIP. Enter them on Subvert's form.

If the release is not ready, no file is downloaded. A dialog **The Subvert ZIP cannot be made yet** lists every reason:

| Reason                                    | What to do                                    |
| ----------------------------------------- | --------------------------------------------- |
| The release has no cover.                 | Upload a cover.                               |
| The cover is 10 MB or larger.             | Upload a smaller cover.                       |
| The release has no tracks.                | Add tracks on the release form.               |
| A track has no audio file.                | Upload audio and attach it to the track.      |
| A track only has audio in another format. | Upload an MP3, FLAC, WAV or AIFF file for it. |
| A file is missing from storage.           | Upload it again.                              |
| The files add up to more than 2 GB.       | Subvert does not accept larger ZIP files.     |

## Good to know

- Deleting a file removes it from storage for good, together with its playback copy and thumbnails. Download it first if you have no other copy. Deleting a release deletes all its files the same way.
- Leaving the release page during an upload cancels the file being sent and all files after it. Files already finished are kept.
- Uploads are sent in one request per file and cannot be resumed.
- An image is never made the cover automatically. Images uploaded with **Detect type** become Artwork.
- Changing a file from Audio to another type removes its track and its playback copy. Changing a file to Audio starts a conversion.
- Thumbnails need image support on the server. Without it, the full-size cover is loaded instead. Images of more than 40 megapixels are not reduced, so such a cover does not show in lists, in the player or on the release page; the original can still be opened from the file table.
- The **Metadata CSV** button in the release list always exports all releases. Search and filters of the list do not apply to it.
- Packages are built while you wait. For a release with several gigabytes of masters this can take minutes; keep the tab open. The server needs free temporary space of about the size of the release.
- A file whose stored copy is missing is left out of the full package without a message. The Subvert ZIP reports it instead.
- If a master is replaced while its track is in the queue, press **Play now** on the track again to load the new file.
