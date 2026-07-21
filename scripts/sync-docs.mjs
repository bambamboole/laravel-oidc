#!/usr/bin/env node
// Copies packages/client/docs/content/*.md into docs/content/docs/client/ so
// the Starlight site can render the client package's docs alongside the
// server and ui docs. Runs before astro build/dev via the `docs:sync` npm
// script. The destination directory is gitignored — it is fully regenerated
// on every run, never hand-edited.
import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { basename, extname, join } from "node:path";
import { fileURLToPath } from "node:url";

const rootDir = fileURLToPath(new URL("..", import.meta.url));
const sourceDir = join(rootDir, "packages/client/docs/content");
const destDir = join(rootDir, "docs/content/docs/client");

const FRONTMATTER_RE = /^---\r?\n([\s\S]*?)\r?\n---\r?\n?/;

function titleCaseFromFilename(filename) {
    return basename(filename, extname(filename))
        .split("-")
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

function firstHeading(body) {
    const match = body.match(/^#\s+(.+)$/m);

    return match ? match[1].trim() : null;
}

// Starlight requires at minimum a `title` frontmatter key. The client
// markdown already ships one, but this is defensive: any file missing
// frontmatter (or missing just the title) gets one injected, derived from
// its first `#` heading and falling back to the filename.
function ensureFrontmatterTitle(filename, content) {
    const match = content.match(FRONTMATTER_RE);

    if (match) {
        const frontmatter = match[1];

        if (/^title:/m.test(frontmatter)) {
            return content;
        }

        const title = firstHeading(content.slice(match[0].length)) ?? titleCaseFromFilename(filename);

        return content.replace(FRONTMATTER_RE, `---\ntitle: ${title}\n${frontmatter}\n---\n`);
    }

    const title = firstHeading(content) ?? titleCaseFromFilename(filename);

    return `---\ntitle: ${title}\n---\n\n${content}`;
}

if (!existsSync(sourceDir)) {
    console.error(`docs:sync — source directory not found: ${sourceDir}`);
    process.exit(1);
}

rmSync(destDir, { recursive: true, force: true });
mkdirSync(destDir, { recursive: true });

const files = readdirSync(sourceDir).filter((file) => file.endsWith(".md"));

for (const file of files) {
    const content = readFileSync(join(sourceDir, file), "utf8");
    writeFileSync(join(destDir, file), ensureFrontmatterTitle(file, content));
}

console.log(`docs:sync — copied ${files.length} file(s) from ${sourceDir} to ${destDir}`);
