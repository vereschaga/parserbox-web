const fs = require('fs');
const path = require('path');

/**
 * Recursively copies a directory and its contents.
 * @param {string} sourceDir - Path to the source directory.
 * @param {string} destDir - Path to the destination directory.
 */
function copyDirectory(sourceDir, destDir) {
    if (!fs.existsSync(sourceDir)) {
        throw new Error(`Source directory "${sourceDir}" does not exist.`);
    }

    if (!fs.existsSync(destDir)) {
        fs.mkdirSync(destDir, { recursive: true });
    }

    const entries = fs.readdirSync(sourceDir, { withFileTypes: true });

    entries.forEach((entry) => {
        const sourcePath = path.join(sourceDir, entry.name);
        const destPath = path.join(destDir, entry.name);

        if (entry.isDirectory()) {
            copyDirectory(sourcePath, destPath);
        } else if (entry.isFile() || entry.isSymbolicLink()) {
            fs.copyFileSync(sourcePath, destPath);
        }
    });
}

const sourceDirPath = 'node_modules';
const destinationDirPath = 'src/assets/common/vendors';

const jquerySource = path.resolve(__dirname, `${sourceDirPath}/jquery`);
const jqueryDestination = path.resolve(
    __dirname,
    `${destinationDirPath}/jquery`
);

const jqueryCookieSource = path.resolve(
    __dirname,
    `${sourceDirPath}/jquery.cookie`
);
const jqueryCookieDestination = path.resolve(
    __dirname,
    `${destinationDirPath}/jquery.cookie`
);

const requireJsSource = path.resolve(__dirname, `${sourceDirPath}/requirejs`);
const requireJsDestination = path.resolve(
    __dirname,
    `${destinationDirPath}/requirejs`
);

const jqueryUiSource = path.resolve(__dirname, `${sourceDirPath}/jquery-ui`);
const jqueryUiDestination = path.resolve(
    __dirname,
    `${destinationDirPath}/jqueryui`
);

const jsonViewerSource = path.resolve(
    __dirname,
    `${sourceDirPath}/@awardwallet/jquery.json-viewer`
);
const jsonViewerDestination = path.resolve(
    __dirname,
    `${destinationDirPath}/jquery.json-viewer`
);

try {
    copyDirectory(jquerySource, jqueryDestination);
    copyDirectory(jqueryCookieSource, jqueryCookieDestination);
    copyDirectory(requireJsSource, requireJsDestination);
    copyDirectory(jqueryUiSource, jqueryUiDestination);
    copyDirectory(jsonViewerSource, jsonViewerDestination);
    console.log('Directories copied successfully');
} catch (error) {
    console.error(`Error: ${error.message}`);
}
