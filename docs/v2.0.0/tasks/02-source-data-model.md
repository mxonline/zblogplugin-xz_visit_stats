# xz_visit_stats v2.0.0 Task 02

## Source analysis data model

Goal: prepare SEO source analysis capability.

New data dimensions:

- referer_url
- referer_domain
- source_type
- search_engine
- keyword
- landing_page

Source categories:

- Search engine
- External link
- Social media
- Direct access
- Unknown

Requirements:

- Existing logs remain readable.
- Collection layer should avoid slowing page requests.
- Large URL fields need safe storage and display handling.

Related module:

SEO Analysis Center.
