# private_html/abad-data/materials/ (stub) — or private_html/abad-config/materials/ (fallback, writable by the SSH master user)

Course material files are served ONLY through `api/material.php?id=<id>` after an auth +
entitlement check (abd_entitlements kind=material). Never place them under public_html.

Upload to `…/private_html/abad-data/materials/` with these names (see ABD_MATERIALS in api/_services_lib.php):

network-camp.pdf, ccna.pdf, network-plus.pdf, security-plus.pdf, linux-plus.pdf, ccnp.pdf, mcsa.pdf, nse4.pdf, rhcsa.pdf

Permissions: dir 750, files 640, owned by the app user.
