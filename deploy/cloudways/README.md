# Cloudways-only exam gate deployment
On Cloudways the public `<name>-exam.html` files are REPLACED by these stubs (redirect to `api/exam.php?id=<name>`),
and the real exam pages are copied to `~/applications/gabascwgon/private_html/abad-data/exams/` (outside the web root),
where only api/exam.php serves them to entitled trainees. The repo keeps the originals (source of truth for questions).
