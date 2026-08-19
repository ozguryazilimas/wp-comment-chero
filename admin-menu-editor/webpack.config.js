import * as url from "url";
import {makeConfig} from "./dev/shared/webpack.config.base.js";

// noinspection JSUnusedGlobalSymbols - Webpack uses this function to get the config.
export default (env, argv) => {
	const __filename = url.fileURLToPath(import.meta.url);

	return makeConfig(
		__filename,
		{
			//TODO: Webpack entry points go here.
		},
		argv
	);
};