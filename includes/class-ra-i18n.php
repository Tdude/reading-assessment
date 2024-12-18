<?php
/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package    ReadingAssessment
 * @subpackage ReadingAssessment/includes
 */

class Reading_Assessment_i18n {

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'reading-assessment',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    /**
     * Register translatable strings.
     * This method can be used to register strings that need translation but aren't
     * picked up automatically by translation tools.
     */
    public function register_translatable_strings() {
        // Error messages
        __('Recording failed to save. Please try again.', 'reading-assessment');
        __('Your browser does not support audio recording.', 'reading-assessment');
        __('Please allow microphone access to record.', 'reading-assessment');
        __('Recording time limit exceeded.', 'reading-assessment');
        __('Please complete all questions before submitting.', 'reading-assessment');

        // Success messages
        __('Recording saved successfully.', 'reading-assessment');
        __('Assessment completed successfully.', 'reading-assessment');
        __('Passage added successfully.', 'reading-assessment');
        __('Question added successfully.', 'reading-assessment');

        // Form labels
        __('Start Recording', 'reading-assessment');
        __('Stop Recording', 'reading-assessment');
        __('Submit Answers', 'reading-assessment');
        __('Time Remaining:', 'reading-assessment');
        __('Your Score:', 'reading-assessment');

        // Admin interface
        __('Reading Assessment Settings', 'reading-assessment');
        __('Manage Passages', 'reading-assessment');
        __('Manage Questions', 'reading-assessment');
        __('View Results', 'reading-assessment');
        __('Add New Passage', 'reading-assessment');
        __('Add New Question', 'reading-assessment');
        __('Edit Passage', 'reading-assessment');
        __('Edit Question', 'reading-assessment');
        __('Delete Passage', 'reading-assessment');
        __('Delete Question', 'reading-assessment');

        // Result labels
        __('Participant', 'reading-assessment');
        __('Date Completed', 'reading-assessment');
        __('Raw Score', 'reading-assessment');
        __('Normalized Score', 'reading-assessment');
        __('Recording Duration', 'reading-assessment');
        __('Correct Answers', 'reading-assessment');
        __('Total Questions', 'reading-assessment');

        // Confirmation messages
        __('Are you sure you want to delete this passage?', 'reading-assessment');
        __('Are you sure you want to delete this question?', 'reading-assessment');
        __('Are you sure you want to stop recording?', 'reading-assessment');

        // Delete soundfile
        __('Radera', 'reading-assessment');
        __('Är du säker på att du vill radera denna inspelning?', 'reading-assessment');
        __('Inspelningen har raderats', 'reading-assessment');
        __('Kunde inte radera inspelningen', 'reading-assessment');
        __('Ett fel uppstod vid kommunikation med servern', 'reading-assessment');
    }
}