/**
 * Extends the @wordpress/scripts Jest config: our tests live in tests/js
 * and use @testing-library/jest-dom matchers.
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],
	moduleNameMapper: {
		'\\.(scss|css)$': require.resolve(
			'@wordpress/jest-preset-default/scripts/style-mock.js'
		),
		// Resolve the @wordpress packages this plugin imports to their built
		// CommonJS output — some package.json entry fields would otherwise
		// point Jest at untranspiled TypeScript sources.
		'^@wordpress/(api-fetch|block-editor|blocks|components|compose|data|dom-ready|editor|element|hooks|html-entities|i18n|notices|plugins|rich-text|url)$':
			'<rootDir>/node_modules/@wordpress/$1/build/index.cjs',
	},
	// Some dependencies ship untransformed ESM: uuid (nested under
	// @wordpress/components) and ESM-only @wordpress packages (theme).
	transform: {
		'\\.m?[jt]sx?$': require.resolve(
			'@wordpress/scripts/config/babel-transform'
		),
	},
	transformIgnorePatterns: [
		'/node_modules/(?!(.*[\\\\/])?uuid[\\\\/]|@wordpress[\\\\/][^\\\\/]+[\\\\/]build-module[\\\\/])',
	],
	setupFilesAfterEnv: [
		...( defaultConfig.setupFilesAfterEnv || [] ),
		'<rootDir>/tests/js/setup.js',
	],
};
