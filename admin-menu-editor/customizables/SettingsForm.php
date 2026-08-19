<?php

namespace YahnisElsts\AdminMenuEditor\Customizable;

use WP_Error;
use YahnisElsts\AdminMenuEditor\Customizable\Builders\FormBuilder;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\FormTableRenderer;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Utils\Forms\GenericSettingsForm;
use YahnisElsts\AdminMenuEditor\Utils\Forms\ParsedFormSubmission;
use YahnisElsts\AdminMenuEditor\WireDSL\WireContext;

class SettingsForm extends GenericSettingsForm {
	const DIE_ON_ERRORS = 1;
	const STORE_ERRORS = 2;

	/**
	 * Skip fields that are not present in the update request. The corresponding
	 * settings won't be changed.
	 */
	const SKIP_MISSING_FIELDS = 10;
	/**
	 * When a setting doesn't have a corresponding field in the update request,
	 * use an empty string in place of the missing field.
	 */
	const TREAT_MISSING_FIELDS_AS_EMPTY = 20;

	/**
	 * @var FormConfig
	 */
	protected $config;

	protected $reservedFields = ['action', '_wpnonce', '_ajax_nonce', '_wp_http_referer'];

	public function __construct(FormConfig $config) {
		if ( !$config->renderer ) {
			$config->renderer = new FormTableRenderer();
		}

		parent::__construct($config);
	}

	public function output() {
		if ( $this->config->formElementId !== null ) {
			$formId = $this->config->formElementId;
		} else {
			$formId = 'ame-struct-form-' . time();
		}

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- HtmlHelper::tag() escapes attributes.
		echo HtmlHelper::tag('form', array(
			'action' => $this->config->submitUrl,
			'method' => $this->config->method,
			'id'     => $formId,
		));
		//phpcs:enable

		$renderer = $this->config->renderer;
		$renderer->renderStructure($this->config->structure, new WireContext());

		if ( !empty($this->config->action) ) {
			//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo HtmlHelper::tag('input', array(
				'type'  => 'hidden',
				'name'  => 'action',
				'value' => $this->config->action,
			));
			//phpcs:enable
			wp_nonce_field($this->config->action);
		}

		if ( $this->config->defaultSubmitButtonEnabled ) {
			submit_button('Save Changes');
		}

		echo '</form>';

		$renderer->enqueueDependencies('#' . $formId);
	}

	public static function builder($action = null): FormBuilder {
		return (new FormBuilder())->action($action);
	}

	public static function builderFor(\ameModule $module): FormBuilder {
		return (new FormBuilder())->initFromModule($module);
	}

	//region Update request handling
	public function handleUpdateRequest($requestParams, $queryParams = []) {
		$submission = $this->preprocessSubmission($requestParams, $queryParams);

		//Check request permissions.
		if ( !empty($this->config->permissionCallback) ) {
			$permissionStatus = call_user_func($this->config->permissionCallback, $submission->getRequestParams());
			if ( !$permissionStatus ) {
				$this->handleError(new WP_Error(
					'ame_permission_denied',
					'You do not have sufficient permissions to perform this operation.'
				));
			} else if ( is_wp_error($permissionStatus) ) {
				$this->handleError($permissionStatus);
			}
		}

		//Extract relevant fields from request parameters. For example, "action"
		//and "_wpnonce" are usually reserved and do not contain setting values.
		//We only want parameters that match setting IDs.
		$inputValues = [];
		foreach ($submission->getRequestParams() as $key => $value) {
			if ( in_array($key, $this->reservedFields) ) {
				continue;
			}
			if ( isset($this->config->settings[$key]) ) {
				$inputValues[$key] = $value;
			}
		}

		//Optionally, substitute missing fields with empty values.
		//Settings that are not editable are excluded.
		if ( $this->config->missingFieldHandling === self::TREAT_MISSING_FIELDS_AS_EMPTY ) {
			$inputValues = $this->substituteEmptyValues($this->config->settings, $inputValues);
		}

		list($errors, $sanitizedValues) = $this->checkAllInputs($inputValues, $this->config->stopOnFirstError);

		//Can we update any settings?
		$settingsUpdated = false;
		if ( !empty($sanitizedValues) && (empty($errors) || $this->config->partialUpdatesAllowed) ) {
			//Update settings.
			$updatedSettings = [];
			foreach ($sanitizedValues as $settingId => $value) {
				$this->config->settings[$settingId]->update($value);
				$updatedSettings[] = $this->config->settings[$settingId];
			}

			//Send any queued update notifications.
			Settings\AbstractSetting::sendPendingNotifications();

			//Run the post-processing callback.
			if ( !empty($this->config->postProcessingCallback) ) {
				call_user_func($this->config->postProcessingCallback, $sanitizedValues, $this->config->settings);
			}

			//Save settings.
			Settings\AbstractSetting::saveAll($updatedSettings);
			$settingsUpdated = true;
		}

		if ( !empty($errors) ) {
			//Error! But could also be a partial success.
			$this->handleUpdateErrors($errors, $submission, $settingsUpdated);
		} else if ( $settingsUpdated ) {
			//Success!
			$this->performSuccessRedirect($submission);
		} else {
			//No errors and no changes. This is probably an error in itself because the user
			//wouldn't have submitted the form if they didn't intend to save something.
			$this->handleError(new WP_Error(
				'ame_no_changes',
				'There were no validation errors, but no changes were made to the settings.'
				. ' This is unexpected and may be a bug.'
			));
		}
	}

	/**
	 * @param WP_Error|WP_Error[] $error
	 * @param ParsedFormSubmission|null $submission
	 * @param bool $isPartialSuccess
	 * @return void
	 */
	protected function handleUpdateErrors($error, ?ParsedFormSubmission $submission = null, bool $isPartialSuccess = false) {
		if ( $this->config->errorReporting === self::DIE_ON_ERRORS ) {

			$settingsById = $this->config->settings;
			if ( is_array($error) ) {
				$messageLines = [];
				foreach ($error as $settingId => $singleError) {
					foreach ($singleError->get_error_messages() as $singleMessage) {
						$messageLines[] = esc_html(sprintf(
							'%s: %s',
							//Add setting names to error messages.
							isset($settingsById[$settingId])
								? $settingsById[$settingId]->getLabel()
								: (!empty($settingId) ? $settingId : 'Error'),
							$singleMessage
						));
					}
				}

				$message = implode("<br>\n", $messageLines);
				//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Individual lines are escaped above.
				wp_die($message);
			} else if ( is_wp_error($error) ) {
				$this->handleError($error);
			} else {
				throw new \LogicException('Invalid error type passed to handleUpdateErrors().');
			}

		} else if ( $this->config->errorReporting === self::STORE_ERRORS ) {

			$errors = is_array($error) ? $error : array($error);
			$serializedErrors = wp_json_encode(array_map([self::class, 'errorToArray'], $errors));
			set_transient($this->config->errorTransientName, $serializedErrors, 120);

			if ( $isPartialSuccess ) {
				$this->performSuccessRedirect($submission);
			} else {
				$this->performRedirect($submission);
			}
		} else {
			throw new \LogicException("Invalid error mode: {$this->config->errorReporting}");
		}
	}

	/**
	 * @param array<string,AbstractSetting>|\Traversable $settingsById
	 * @param array<string,mixed> $inputValues
	 * @return array<string,mixed>
	 */
	protected function substituteEmptyValues($settingsById, array $inputValues): array {
		foreach ($settingsById as $settingId => $setting) {
			if ( !array_key_exists($settingId, $inputValues) && $setting->isEditableByUser() ) {
				if ( $setting instanceof Settings\AbstractStructSetting ) {
					$inputValues[$settingId] = array();
				} else {
					$inputValues[$settingId] = '';
				}
			}

			if ( $setting instanceof Settings\AbstractStructSetting ) {
				$inputValues[$settingId] = $this->substituteEmptyValues(
					$setting,
					$inputValues[$settingId]
				);
			}
		}
		return $inputValues;
	}

	protected function checkAllInputs($inputValues, $stopOnError = false): array {
		$errors = [];
		$sanitizedValues = [];

		foreach ($inputValues as $settingId => $value) {
			if ( !isset($this->config->settings[$settingId]) ) {
				continue;
			}
			$setting = $this->config->settings[$settingId];

			//Validate and sanitize.
			$validationResult = $setting->validateFormValue(new WP_Error(), $value, $stopOnError);
			if ( is_wp_error($validationResult) && ($validationResult->has_errors()) ) {
				$errors[$settingId] = $validationResult;
				if ( $stopOnError ) {
					break;
				}
			} else {
				$sanitizedValues[$settingId] = $validationResult;
			}

			//Check setting permissions.
			if ( !$setting->isEditableByUser() ) {
				$errors[$settingId] = new WP_Error(
					'ame_permission_denied',
					'You do not have permission to change this setting.'
				);
				if ( $stopOnError ) {
					break;
				}
			}
		}

		return [$errors, $sanitizedValues];
	}
	//endregion
}