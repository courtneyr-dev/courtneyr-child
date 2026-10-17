<?php
/**
 * `php tests/php/sticky-note-test.php`: checks the Note sticky adapter without WordPress.
 *
 * The golden below was captured from inc/sticky-note.php at fe5b8a1 before
 * the change; sha256 ab2d8d323e99d16958d18bb84667ee6ec2d32aeb4dadb6521115df3435d21688
 * of the TSV.
 *
 * @package CourtneyrChild
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COURTNEYR_CHILD_URI', 'https://example.test/theme' );
	define( 'COURTNEYR_CHILD_VERSION', 'test' );
	define( 'CR_NOTE_THEME_DIR', dirname( __DIR__, 2 ) );

	$GLOBALS['cr_note_hooks']        = array();
	$GLOBALS['cr_note_block_styles'] = array();
	$GLOBALS['cr_note_posts']        = array();
	$GLOBALS['cr_note_current_id']   = 0;
	$GLOBALS['cr_note_format']       = '';

	class WP_Post {
		public int $ID;
		public string $post_title;

		/** Build a small post stand-in. */
		public function __construct( int $id, string $title ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}

	class WP_Block {
		/** @var array<string, mixed> */
		public array $context;

		/** @param array<string, mixed> $context Block context. */
		public function __construct( array $context = array() ) {
			$this->context = $context;
		}
	}

	/** Record an action callback. */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_note_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a filter callback. */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
		$GLOBALS['cr_note_hooks'][] = array( (string) $hook, $callback, (int) $priority, (int) $accepted_args );
	}

	/** Record a registered block style. */
	function register_block_style( $block_name, $properties ): void {
		$GLOBALS['cr_note_block_styles'][] = array( (string) $block_name, $properties );
	}

	/** Translate a string. */
	function __( $text, $domain = null ): string {
		return (string) $text;
	}

	/** Fetch a post stand-in. */
	function get_post( $post = null ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = is_numeric( $post ) ? (int) $post : (int) $GLOBALS['cr_note_current_id'];
		return $GLOBALS['cr_note_posts'][ $id ] ?? null;
	}

	/** Current loop post ID. */
	function get_the_ID(): int {
		return (int) $GLOBALS['cr_note_current_id'];
	}

	/** Current post format. */
	function get_post_format( $post = null ): string {
		return (string) $GLOBALS['cr_note_format'];
	}

	/** Strip tags like WordPress for the helpers loaded below. */
	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}

	/** Attribute escaping used by inc/stamps.php. */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** HTML escaping used by inc/stamps.php. */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/** Front-end request flag, unused by these checks. */
	function is_admin(): bool {
		return false;
	}

	/** Taxonomy request flag, unused by these checks. */
	function is_tax( $taxonomy = '', $term = '' ): bool {
		return false;
	}

	/** Enqueue stand-in, unused by these checks. */
	function wp_enqueue_style( $handle, $src = '', $dependencies = array(), $version = false ): void {}

	require CR_NOTE_THEME_DIR . '/inc/stamps.php';
	require CR_NOTE_THEME_DIR . '/inc/sticky-note.php';
}

namespace Courtneyr\Child\StickyNote\Tests {

	use function Courtneyr\Child\Stamps\seed;
	use function Courtneyr\Child\StickyNote\board_item;
	use function Courtneyr\Child\StickyNote\register_board_styles;
	use function Courtneyr\Child\StickyNote\sticky_style;

	const GOLDEN_STICKY_STYLE = <<<'TSV'
1	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
2	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
3	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
4	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
5	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
6	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
7	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
8	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
9	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
10	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
11	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
12	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
13	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
14	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
15	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
16	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
17	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
18	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
19	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
20	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
21	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
22	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
23	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
24	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
25	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
26	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
27	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
28	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
29	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
30	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
31	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
32	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
33	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
34	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
35	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
36	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
37	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
38	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
39	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
40	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
41	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
42	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
43	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
44	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
45	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
46	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
47	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
48	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
49	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
50	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
51	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
52	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
53	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
54	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
55	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
56	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
57	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
58	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
59	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
60	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
61	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
62	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
63	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
64	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
65	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
66	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
67	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
68	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
69	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
70	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
71	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
72	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
73	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
74	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
75	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
76	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
77	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
78	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
79	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
80	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
81	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
82	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
83	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
84	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
85	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
86	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
87	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
88	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
89	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
90	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
91	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
92	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
93	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
94	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
95	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
96	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
97	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
98	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
99	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
100	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
101	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
102	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
103	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
104	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
105	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
106	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
107	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
108	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
109	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
110	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
111	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
112	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
113	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
114	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
115	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
116	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
117	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
118	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
119	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
120	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
121	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
122	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
123	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
124	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
125	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
126	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
127	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
128	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
129	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
130	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
131	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
132	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
133	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
134	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
135	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
136	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
137	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
138	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
139	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
140	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
141	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
142	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
143	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
144	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
145	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
146	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
147	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
148	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
149	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
150	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
151	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
152	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
153	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
154	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
155	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
156	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
157	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
158	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
159	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
160	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
161	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
162	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
163	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
164	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
165	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
166	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
167	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
168	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
169	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
170	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
171	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
172	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
173	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
174	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
175	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
176	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
177	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
178	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
179	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
180	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
181	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
182	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
183	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
184	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
185	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
186	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
187	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
188	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
189	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
190	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
191	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
192	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
193	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
194	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
195	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
196	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
197	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
198	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
199	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
200	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
201	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
202	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
203	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
204	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
205	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
206	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
207	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
208	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
209	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
210	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
211	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
212	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
213	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
214	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
215	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
216	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
217	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
218	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
219	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
220	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
221	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
222	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
223	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
224	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
225	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
226	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
227	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
228	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
229	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
230	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
231	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:1.1deg"
232	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
233	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
234	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
235	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
236	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
237	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
238	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
239	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
240	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
241	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
242	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
243	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
244	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
245	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
246	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
247	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
248	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
249	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
250	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
251	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
252	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
253	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
254	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
255	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
256	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
257	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
258	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
259	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
260	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
261	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
262	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
263	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-1.2deg"
264	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
265	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
266	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
267	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
268	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
269	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
270	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
271	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
272	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
273	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
274	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
275	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
276	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
277	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
278	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
279	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
280	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
281	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
282	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
283	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
284	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
285	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
286	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
287	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
288	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
289	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
290	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
291	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
292	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
293	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
294	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
295	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:0.6deg"
296	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
297	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
298	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
299	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
300	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
301	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
302	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
303	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
304	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
305	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
306	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
307	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
308	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
309	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
310	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
311	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
312	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
313	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
314	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
315	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
316	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
317	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
318	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
319	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
320	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
321	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
322	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
323	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
324	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
325	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
326	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
327	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-0.5deg"
328	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
329	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
330	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
331	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
332	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
333	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
334	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
335	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
336	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
337	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
338	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
339	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
340	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
341	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
342	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
343	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
344	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
345	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
346	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
347	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
348	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
349	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
350	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
351	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
352	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
353	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
354	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
355	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
356	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
357	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
358	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
359	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:1.1deg"
360	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
361	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
362	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
363	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
364	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
365	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
366	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
367	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
368	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
369	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
370	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
371	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
372	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
373	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
374	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
375	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
376	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
377	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
378	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
379	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
380	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
381	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
382	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
383	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
384	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
385	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
386	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
387	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
388	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
389	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
390	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
391	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-1.2deg"
392	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
393	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
394	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
395	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
396	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
397	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
398	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
399	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-1.2deg"
400	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
401	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
402	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
403	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
404	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
405	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
406	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
407	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:-1.2deg"
408	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
409	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
410	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
411	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
412	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
413	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
414	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
415	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-1.2deg"
416	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
417	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
418	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
419	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
420	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
421	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
422	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
423	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:0.6deg"
424	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
425	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
426	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
427	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
428	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
429	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
430	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
431	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:0.6deg"
432	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
433	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
434	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
435	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
436	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
437	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
438	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
439	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:0.6deg"
440	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
441	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
442	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
443	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
444	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
445	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
446	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
447	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:0.6deg"
448	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
449	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
450	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
451	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
452	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
453	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
454	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
455	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:-0.5deg"
456	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
457	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
458	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
459	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
460	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
461	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
462	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
463	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:-0.5deg"
464	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
465	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
466	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
467	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
468	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
469	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
470	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
471	style="--cr-sticky-paper:#d6ecf6;--cr-sticky-tilt:-0.5deg"
472	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
473	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
474	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
475	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
476	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
477	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
478	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
479	style="--cr-sticky-paper:#ebebeb;--cr-sticky-tilt:-0.5deg"
480	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
481	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
482	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
483	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
484	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
485	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
486	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
487	style="--cr-sticky-paper:#ffe8a6;--cr-sticky-tilt:1.1deg"
488	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
489	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
490	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
491	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
492	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
493	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
494	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
495	style="--cr-sticky-paper:#fee2c3;--cr-sticky-tilt:1.1deg"
496	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
497	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
498	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
499	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
500	style="--cr-sticky-paper:#dedaf2;--cr-sticky-tilt:1.1deg"
TSV;

	$failures = array();

	/** Print one result and remember a failure. */
	function check( bool $ok, string $message ): void {
		global $failures;
		echo ( $ok ? 'ok   ' : 'FAIL ' ), $message, "\n";
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	/** Whether one callback has the exact hook registration. */
	function has_hook( string $hook, string $callback, int $priority, int $accepted_args ): bool {
		return in_array( array( $hook, $callback, $priority, $accepted_args ), $GLOBALS['cr_note_hooks'], true );
	}

	/** Read the paper custom property's value. */
	function paper( string $style ): string {
		return preg_match( '/--cr-sticky-paper:([^;\"]+)/', $style, $matches ) ? $matches[1] : '';
	}

	/** Load Core's real HTML Tag Processor when a checkout is available. */
	function load_html_api(): bool {
		$core = getenv( 'CR_WP_CORE' ) ?: (string) getenv( 'HOME' ) . '/.wp-tests/wordpress';
		$api  = $core . '/wp-includes/html-api';
		if ( ! is_dir( $api ) ) {
			return false;
		}
		require_once $core . '/wp-includes/compat.php';
		require_once $core . '/wp-includes/utf8.php';
		require_once $core . '/wp-includes/kses.php';
		foreach ( array( 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-attribute-token.php', 'class-wp-html-tag-processor.php' ) as $file ) {
			require_once $api . '/' . $file;
		}
		return class_exists( '\\WP_HTML_Tag_Processor' );
	}

	$golden_lines = explode( "\n", GOLDEN_STICKY_STYLE );
	$golden_ok    = 500 === count( $golden_lines );
	$outputs      = array();
	foreach ( $golden_lines as $line_number => $line ) {
		$parts = explode( "\t", $line, 2 );
		$seed  = $line_number + 1;
		if ( 2 !== count( $parts ) || (string) $seed !== $parts[0] || sticky_style( $seed ) !== $parts[1] ) {
			$golden_ok = false;
			break;
		}
		$outputs[] = $parts[1];
	}
	check( $golden_ok, 'sticky_style() matches all 500 fe5b8a1 golden outputs byte for byte' );
	check( 'ab2d8d323e99d16958d18bb84667ee6ec2d32aeb4dadb6521115df3435d21688' === hash( 'sha256', GOLDEN_STICKY_STYLE . "\n" ), 'embedded golden TSV has the captured sha256' );
	check( 20 === count( array_unique( $outputs ) ), 'the golden reaches all 20 paper and tilt combinations' );

	$fixed_ok = true;
	for ( $stable_seed = 1; $stable_seed <= 500; $stable_seed++ ) {
		$default = sticky_style( $stable_seed );
		$fixed   = sticky_style( $stable_seed, '0deg' );
		if ( paper( $default ) !== paper( $fixed ) || ! str_contains( $fixed, '--cr-sticky-tilt:0deg' ) || sticky_style( $stable_seed, null ) !== $default ) {
			$fixed_ok = false;
			break;
		}
	}
	check( $fixed_ok, 'a fixed 0deg tilt preserves every seeded paper and null preserves the default' );

	if ( ! load_html_api() ) {
		echo "SKIP board_item(): set CR_WP_CORE to a WordPress checkout\n";
	} else {
		$post                                  = new \WP_Post( 73, 'A field note' );
		$GLOBALS['cr_note_posts'][ $post->ID ] = $post;
		$GLOBALS['cr_note_current_id']         = $post->ID;
		$instance                              = new \WP_Block( array( 'postId' => $post->ID ) );
		$html                                  = '<div class="pkiw-note-entry pkiw-note-entry--titled is-style-cr-sticky"><h3>Field note</h3></div>';
		$expected                              = '<div class="cr-sticky-artifact cr-sticky-artifact--board" ' . sticky_style( seed( (string) $post->ID, $post->post_title ), '0deg' ) . '><div class="cr-sticky cr-sticky--board">' . $html . '</div><span class="cr-sticky__curl"></span></div>';
		$wrapped                               = board_item( $html, array( 'ignored' => true ), $instance );
		check( $expected === $wrapped, 'board_item() wraps once with the seeded fixed style and preserves plugin markup byte for byte' );
		check( 1 === substr_count( $wrapped, 'cr-sticky-artifact--board' ) && 1 === substr_count( $wrapped, 'cr-sticky--board' ) && 1 === substr_count( $wrapped, 'cr-sticky__curl' ), 'board_item() prints one artifact, board sticky and curl' );

		$unstyled = '<div class="pkiw-note-entry pkiw-note-entry--titled"><span class="is-style-cr-sticky">descendant</span></div>';
		check( $unstyled === board_item( $unstyled, array(), $instance ), 'board_item() ignores a style class found only on a descendant' );
		$later = '<div class="pkiw-note-entry">first</div><div class="pkiw-note-entry is-style-cr-sticky">later</div>';
		check( $later === board_item( $later, array(), $instance ), 'board_item() checks only the first pkiw-note-entry tag' );
		$text_only = '<div class="pkiw-note-entry">is-style-cr-sticky</div>';
		check( $text_only === board_item( $text_only, array(), $instance ), 'board_item() ignores a style class written as text' );
		$missing = '<article class="is-style-cr-sticky">No note entry</article>';
		check( $missing === board_item( $missing, array(), $instance ), 'board_item() returns markup without pkiw-note-entry unchanged' );
		$suffix = '<div class="pkiw-note-entry--titled is-style-cr-sticky">Suffix only</div>';
		check( $suffix === board_item( $suffix, array(), $instance ), 'pkiw-note-entry--titled does not satisfy the exact class-token gate' );

		$GLOBALS['cr_note_format'] = 'aside';
		check( $expected === board_item( $html, array(), $instance ), 'an Aside-format note is still wrapped on the archive board' );
		$GLOBALS['cr_note_posts'] = array();
		check( $html === board_item( $html, array(), $instance ), 'board_item() returns the input unchanged when no post exists' );
	}

	$source = (string) file_get_contents( CR_NOTE_THEME_DIR . '/inc/sticky-note.php' );
	check( false === strpos( $source, 'attrs' ), 'inc/sticky-note.php contains no attrs read' );
	check( function_exists( 'Courtneyr\\Child\\StickyNote\\stream_card' ) && has_hook( 'render_block_post-kinds-indieweb/stream-card', 'Courtneyr\\Child\\StickyNote\\stream_card', 10, 3 ), 'stream_card() remains hooked at priority 10 with three arguments' );
	check( function_exists( 'Courtneyr\\Child\\StickyNote\\single_sticky' ) && has_hook( 'render_block', 'Courtneyr\\Child\\StickyNote\\single_sticky', 20, 2 ), 'single_sticky() remains hooked at priority 20 with two arguments' );
	check( has_hook( 'init', 'Courtneyr\\Child\\StickyNote\\register_board_styles', 10, 1 ), 'register_board_styles() is hooked on init' );
	check( has_hook( 'render_block_post-kinds-indieweb/note-entry', 'Courtneyr\\Child\\StickyNote\\board_item', 10, 3 ), 'board_item() is hooked at priority 10 with three arguments' );
	check( has_hook( 'enqueue_block_assets', 'Courtneyr\\Child\\StickyNote\\enqueue_board_styles', 10, 1 ), 'enqueue_board_styles() is hooked on enqueue_block_assets' );

	$GLOBALS['cr_note_block_styles'] = array();
	register_board_styles();
	check(
		array(
			array( 'core/post-template', array( 'name' => 'cr-note-board', 'label' => 'Note board' ) ),
			array( 'post-kinds-indieweb/note-entry', array( 'name' => 'cr-sticky', 'label' => 'Sticky' ) ),
		) === $GLOBALS['cr_note_block_styles'],
		'register_board_styles() registers the Note board and Sticky styles'
	);

	echo "\n", count( $failures ) ? count( $failures ) . ' failed' : 'all passed', "\n";
	exit( count( $failures ) ? 1 : 0 );
}
