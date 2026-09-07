import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        // Named explicitly rather than left to the default glob: a Sylius application built for
        // manual verification is a plain subdirectory here, and its node_modules are full of
        // other people's test files.
        include: ['tests/JavaScript/**/*.test.js'],
    },
});
