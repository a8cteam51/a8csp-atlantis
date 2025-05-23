const postcssPlugins = require( '@wordpress/postcss-plugins-preset' );

module.exports = ( ctx ) => {
	const isDevelopment = ( 'development' === ctx.env );
	const isSass = ( '.scss' === ctx.file.extname );

	return {
		map: {
			inline: isDevelopment,
			annotation: true
		},
		parser: isSass ? 'postcss-scss' : false,
		plugins: [
			// 1. Inline all @import rules (must go first)
			require( 'postcss-import' ),

			// 2. Enable nested rules in your CSS/SCSS
			require( 'postcss-nested' ),

			// 3. Autoprefixer for vendor prefixes
			require( 'autoprefixer' ),

			// 4. Sass parser for .scss
			...( isSass ? [ require( '@csstools/postcss-sass' ) ] : [] ),

			// 5. WordPress preset (e.g. env, normalization, etc.)
			...postcssPlugins,
            // Additional plugins here.
		]
	};
};
