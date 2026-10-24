# usage: python3 -m unittest test_handles   (from tests/perf)
import unittest
from handles import handle_sets

FIXTURE = """
<link rel='stylesheet' id='courtneyr-quote-book-css' href='https://x.test/wp-content/themes/courtneyr-child/assets/css/cr-quote-book.css?ver=0.7.135' media='print' />
<link rel='stylesheet' id='cr-collection-css' data-cr-href='https://x.test/wp-content/themes/courtneyr-child/assets/css/cr-collection.css?ver=0.7.135' media='print' />
<link rel='stylesheet' id='post-kinds-indieweb-card-css' href='https://x.test/wp-content/plugins/post-kinds-for-indieweb/assets/css/card.css?ver=1' />
<link rel='stylesheet' id='wp-block-library-css' href='https://x.test/wp-includes/css/dist/block-library/style.min.css' />
<link rel='stylesheet' id='cr-plugin-sheet-css' href='https://x.test/wp-content/plugins/other/build/style.css' />
<style id='courtneyr-tokens-css'>:root{}</style>
<style id='cr-collection-inline-css'>.a{}</style>
<style id='wp-block-image-inline-css'>.b{}</style>
"""

class HandleSets(unittest.TestCase):
    def test_theme_handles_both_prefixes_and_no_plugin_sheets(self):
        links, inline = handle_sets(FIXTURE)
        self.assertEqual(links, [
            "courtneyr-quote-book-css|href|cr-quote-book.css",
            "cr-collection-css|data-cr-href|cr-collection.css",
            "cr-collection-css|href|cr-collection.css",  # \bhref matches inside data-cr-href; the saved baselines carry both, so keep it
        ])
        self.assertEqual(inline, ["courtneyr-tokens-css", "cr-collection-inline-css"])

if __name__ == "__main__": unittest.main()
