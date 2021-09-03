# plg_system_articlesubtitleghsvs
- Joomla system plugin. A horrible code chaos from early days. Must be ported to Joomla 4 and reduced step by step because 2 sites still use it.
- It has nothing to do with article subtitles ;-)
- The goal: Reduce it to 1 feature: Display some author informations, copyright hint, below articles.
- Needs a database table that won't be installed automatically.
- Needs special configuration of com_contact categories and entries.
- Has lots of stupid features for nothing.

## Short: It's really not worth to install it or use it. More a killer than helpful.

-----------------------------------------------------

# My personal build procedure (WSL 1, Debian, Win 10)
- Prepare/adapt `./package.json`.
- `cd /mnt/z/git-kram/plg_system_articlesubtitleghsvs`

## node/npm updates/installation
- `npm run g-npm-update-check` or (faster) `ncu`
- `npm run g-ncu-override-json` (if needed) or (faster) `ncu -u`
- `npm install` (if needed)

## Build installable ZIP package
- `node build.js`
- New, installable ZIP is in `./dist` afterwards.
- All packed files for this ZIP can be seen in `./package`. **But only if you disable deletion of this folder at the end of `build.js`**.

### For Joomla update and changelog server
- Create new release with new tag.
- - See release description in `dist/release.txt`.
- Extracts(!) of the update and changelog XML for update and changelog servers are in `./dist` as well. Copy/paste and necessary additions.
