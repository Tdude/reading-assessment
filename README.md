# Reading Assesment WP plugin

This is a show-text, voice recording, save scores plugin for Wordpress

I uploaded all the files to Claude AI. The text below is from there. It hurts to be human but I suppose I just have to get on with it...
The question is, did I follow Clude's suggestions? Hey, I'm still hooman so Hell No! Good suggestions though :)

## Claude AI 2024
Looking at your admin.js code and the associated PHP files, there's definitely an opportunity to create a more generic and maintainable solution.

2025 Copilot:
# Reading Assessment

**Reading Assessment** is a plugin for recording and evaluating reading comprehension. It allows administrators to assign reading passages to users and evaluate their comprehension through audio recordings and questions.

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
2. The recording is automatically uploaded and saved.

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
