# TODO – Area Content Modal Destination Dropdown

- [ ] Confirm lookup data is loaded for destination select (sitemap entries, door sign destinations, item/category options, actions) when Type is link/content/button.
- [ ] Ensure `buildDestinationOptions`/UI actually populates the destination select and shows options for the selected type instead of staying empty.
- [ ] Hook up link-type extras: allow specifying URL plus choosing/storing a link image/icon in both the add form and table rows (render, edit, save payload).
- [ ] Verify the dropdown pulls site-local destinations from field map DB (pages/modals/door signs) and that content rows auto-fill image when available.
- [ ] Test end-to-end in the modal: change Type, see destinations populate, set link URL/image, save mapping, and reload to confirm persistence.
- [ ] NEW: For shortcuts (content/button), pull destination options from Room Manager rooms only; preselect current shortcut target; keep link as URL input.
- [ ] NEW: Audit shortcut buttons opening wrong rooms (e.g., tshirts icon opening tumblers); ensure they open the configured room only and no nested openings.
- [ ] NEW: Remove order column from area content modal; auto-increment order internally when adding/removing.
