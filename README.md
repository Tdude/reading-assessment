# Reading Assesment WP plugin

This is a show-text, voice recording, save scores plugin for Wordpress
You can run it as-is or hook up Groq and/or OpenAI's Whisper to get decent evaluations for read voice. For that you need an API key or run your own at AWS or so.

## @TODO: Check out the Gemini docs

Attach Gemini as a backup AI or use it for re-evaluating sound? 3 lines of code...?
https://ai.google.dev/gemini-api/docs/openai

## @TODO: Refactor JS

Regarding admin.js, there's definitely an opportunity to create a more generic and maintainable solution.


## Features

- Assign reading passages to users
- Record and upload audio responses
- Evaluate comprehension with questions
- User-friendly interface

## Installation

1. Upload the plugin files to the `/wp-content/plugins/reading-assessment` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the settings screen to configure the plugin.

## Usage

### Assigning Passages

1. Navigate to the Reading Assessment admin page.
2. Assign passages to users by selecting a user and choosing passages from the list.
3. Save the assignments.

### Recording Audio

1. Users can record their audio responses directly on the passage page.
2. The recording can be listened to, trimmed and be uploaded/saved. Currently all texts need to have questions/answers set to them for the upload to work, since the functions "ask" for the existence of questions.

### Evaluating Comprehension

1. After recording, users will answer comprehension questions.
2. Administrators can review the audio and answers to assess comprehension.

## Shortcodes

- `[ra_display_passage]` - Display assigned passages for the logged-in user.
- `[ra_audio_recorder]` - Display the audio recorder for the logged-in user.

## Development

### Code Structure

- **Main Plugin File:** `reading-assessment.php`
- **Includes Directory:** Contains core classes and functionality.
- **Public Directory:** Contains public-facing code (shortcodes, JS, CSS).
- **Admin Directory:** Contains admin-facing code (settings, management).

### Error Handling

A centralized error handling function is used to manage errors consistently across the plugin.

### Coding Standards

- PHP: Follows WordPress Coding Standards.
- JavaScript: Uses modern JavaScript features and follows ESLint rules.
- CSS: Follows WordPress CSS Coding Standards.

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or bugfix.
3. Commit your changes with clear and descriptive commit messages.
4. Push your changes to your fork.
5. Open a pull request with a detailed description of your changes.

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support, please open an issue in the GitHub repository or contact the author.

If all else fails you know the drill: look around the web, learn a bunch of stuff resulting in 999 open tabs. Then resort to asking an AI and you're back to bug solving your quick and dirty code again :) Rinse and repeat.
