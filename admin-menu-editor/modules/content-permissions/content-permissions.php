<?php

namespace YahnisElsts\AdminMenuEditor\ContentPermissions;

use YahnisElsts\AdminMenuEditor\Actors\Actor;
use YahnisElsts\AdminMenuEditor\Actors\ActorManager;
use YahnisElsts\AdminMenuEditor\ContentPermissions\Policy\Action;
use YahnisElsts\AdminMenuEditor\ContentPermissions\Policy\ActionRegistry;
use YahnisElsts\AdminMenuEditor\ContentPermissions\Policy\ContentItemPolicy;
use YahnisElsts\AdminMenuEditor\ContentPermissions\Policy\EvaluationResult;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\ModuleSettings;

require_once __DIR__ . '/policy.php';
require_once __DIR__ . '/editor-ui.php';

class ContentPermissionsModule extends \amePersistentModule {
	protected $optionName = 'ame_cpe_settings';

	private $metaBoxUiScript = null;

	private bool $termPermissionsEnabled = false; //Disabled because the feature is not finished.

	public function __construct($menuEditor) {
		$this->settingsWrapperEnabled = true;
		parent::__construct($menuEditor);

		$actorManager = new ActorManager($menuEditor);
		$actionRegistry = new Policy\ActionRegistry();

		$policyStore = new Policy\PolicyStore($actionRegistry);
		$enforcer = new ContentPermissionsEnforcer(
			$actionRegistry,
			$actorManager,
			$policyStore,
			$this
		);

		$settings = $this->loadSettings();
		if ( !empty($settings['enforcementDisabled']) ) {
			$enforcer->disableEnforcement();
		}

		if ( is_admin() ) {
			new UserInterface\ContentPermissionsMetaBox(
				$actionRegistry,
				$actorManager,
				$policyStore,
				$this,
				$enforcer,
				$this->menuEditor->get_settings_page_url()
			);
		}

		//Clear the enabled post type cache when new post types are registered.
		add_action('registered_post_type', function () {
			$this->cachedEnabledPostTypes = null;
		}, 10, 0);

		//Add a "Content permissions" section to the "Settings" tab.
		add_action('admin_menu_editor-settings_page_extra', [$this, 'outputModuleSettings']);
		add_action('admin_menu_editor-settings_changed', [$this, 'handleSettingsFormSubmission']);
	}

	public function enqueuePolicyEditorStyles() {
		$this->enqueueLocalStyle(
			'ame-cpe-mb-style',
			'content-permissions.css'
		);
	}

	public function getMetaBoxScript() {
		if ( $this->metaBoxUiScript === null ) {
			$baseDeps = $this->menuEditor->get_base_dependencies();

			$this->metaBoxUiScript = $this->registerLocalScript(
				'ame-cpe-mb-ui',
				'content-permissions-mb-ui.js',
				[
					'jquery',
					'jquery-ui-tooltip',
					$baseDeps['ame-knockout'],
					$baseDeps['ame-actor-manager'],
					$baseDeps['ame-actor-selector'],
					$baseDeps['ame-lodash'],
				],
				true
			);
		}
		return $this->metaBoxUiScript;
	}

	public function userCanEditPolicyForPost($postId) {
		return (
			$this->menuEditor->current_user_can_edit_menu()
			&& current_user_can('edit_post', $postId)
		);
	}

	public function userCanEditPolicyForTerm($termId): bool {
		return (
			$this->menuEditor->current_user_can_edit_menu()
			&& current_user_can('edit_term', $termId)
		);
	}

	/**
	 * @var null|array<string, mixed>
	 */
	private $cachedEnabledPostTypes = null;

	/**
	 * @return array<string, mixed>
	 */
	public function getEnabledPostTypes() {
		if ( $this->cachedEnabledPostTypes !== null ) {
			return $this->cachedEnabledPostTypes;
		}

		$excludedPostTypes = ['attachment', 'revision', 'wp_block'];

		$potentialPostTypes = get_post_types(['public' => true], 'objects');
		$enabledPostTypes = [];
		foreach ($potentialPostTypes as $postType) {
			if (
				post_type_supports($postType->name, 'editor')
				&& !in_array($postType->name, $excludedPostTypes)
			) {
				$enabledPostTypes[$postType->name] = $postType->name;
			}
		}

		$this->cachedEnabledPostTypes = $enabledPostTypes;
		return $enabledPostTypes;
	}

	/**
	 * @param string|mixed $postType
	 * @return bool
	 */
	public function isPostTypeEnabled($postType) {
		//This method can be called with the return value of get_post_type(), which can be false
		//if the post doesn't exist.
		if ( !is_string($postType) ) {
			return false;
		}

		$enabledPostTypes = $this->getEnabledPostTypes();
		return !empty($enabledPostTypes[$postType]);
	}

	private ?array $cachedEnabledTaxonomies = null;

	/**
	 * @return array<string, mixed>
	 */
	public function getEnabledTaxonomies(): array {
		if ( $this->cachedEnabledTaxonomies !== null ) {
			return $this->cachedEnabledTaxonomies;
		}

		//Currently, all taxonomies associated with enabled post types are considered enabled.
		$enabledPostTypes = $this->getEnabledPostTypes();
		$enabledTaxonomies = [];
		foreach (array_keys($enabledPostTypes) as $postType) {
			$taxonomies = get_object_taxonomies($postType, 'objects');
			foreach ($taxonomies as $taxonomy) {
				$enabledTaxonomies[$taxonomy->name] = $taxonomy->name;
			}
		}

		$this->cachedEnabledTaxonomies = $enabledTaxonomies;
		return $enabledTaxonomies;
	}

	public function createSettingInstances(ModuleSettings $settings) {
		$f = $settings->settingFactory();
		return [
			$f->boolean(
				'enforcementDisabled',
				__('Disable content permissions enforcement', 'admin-menu-editor'),
				[
					'description' => __(
						'You can still edit post and page permissions, but they won\'t have any effect. Useful for troubleshooting.',
						'admin-menu-editor'
					),
				]
			),
			$f->enum(
				'detectCapsWithNonExistentUser',
				[null, true, false],
				__('Detect post type capabilities by checking them with a non-existent user', 'admin-menu-editor'),
				[
					'description' => __(
						'This usually produces more accurate results, but it can cause errors in some plugins and themes. If you see errors in the post editor, try disabling this option.',
						'admin-menu-editor'
					),
					'default'     => null,
				]
			),
		];
	}

	public function outputModuleSettings() {
		$settings = $this->loadSettings();
		if ( !($settings instanceof ModuleSettings) ) {
			return;
		}
		$enforcementDisabled = $settings->getSetting('enforcementDisabled');
		$detectCapsWithNonExistentUser = $settings->getSetting('detectCapsWithNonExistentUser');

		?>
		<tr id="ame-content-permissions-section">
			<th scope="row">
				<?php _ex('Content permissions', '"Settings" tab section', 'admin-menu-editor'); ?>
			</th>
			<td>
				<p>
					<label>
						<input type="checkbox" name="ame_cpe_enforcementDisabled"
							<?php checked($settings['enforcementDisabled']); ?>>
						<?php echo esc_html($enforcementDisabled->getLabel()); ?>

						<?php
						$description = $enforcementDisabled->getDescription();
						if ( $description ) :
							?>
							<br><span class="description"><?php echo esc_html($description); ?></span>
						<?php endif; ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="ame_cpe_detectCapsWithNonExistentUser"
							<?php checked($detectCapsWithNonExistentUser->getValue(true)); ?>>
						<?php echo esc_html($detectCapsWithNonExistentUser->getLabel()); ?>
						<br><span class="description"><?php
							echo esc_html($detectCapsWithNonExistentUser->getDescription()); ?>
						</span>
					</label>
			</td>
		</tr>
		<?php
	}

	public function handleSettingsFormSubmission($post) {
		$settings = $this->loadSettings();
		if ( !($settings instanceof ModuleSettings) ) {
			return;
		}
		$enforcementDisabled = $settings->getSetting('enforcementDisabled');
		$enforcementDisabled->update(!empty($post['ame_cpe_enforcementDisabled']));

		$detectCapsWithNonExistentUser = $settings->getSetting('detectCapsWithNonExistentUser');
		$detectCapsWithNonExistentUser->update(!empty($post['ame_cpe_detectCapsWithNonExistentUser']));

		$settings->save();
	}

	public function areTermPermissionsEnabled(): bool {
		return $this->termPermissionsEnabled;
	}

	public function isSuitableForExport() {
		return false;
	}
}

class ContentPermissionsEnforcer {
	const CONTENT_FILTER_PRIORITY = 96;

	const POST_TO_TERM_ACTION_MAP = [
		ActionRegistry::ACTION_READ          => ActionRegistry::ACTION_READ_ASSOCIATED_OBJECTS,
		ActionRegistry::ACTION_VIEW_IN_LISTS => ActionRegistry::ACTION_VIEW_ASSOCIATED_OBJECTS_IN_LISTS,
		ActionRegistry::ACTION_EDIT          => ActionRegistry::ACTION_EDIT_ASSOCIATED_OBJECTS,
		ActionRegistry::ACTION_DELETE        => ActionRegistry::ACTION_DELETE_ASSOCIATED_OBJECTS,
	];

	/**
	 * @var ActorManager
	 */
	private $actorManager;

	/**
	 * @var Policy\PolicyStore
	 */
	private $policyStore;

	/**
	 * @var Policy\ActionRegistry
	 */
	private $actionRegistry;

	/**
	 * @var ContentPermissionsModule
	 */
	private $module;

	/**
	 * Whether enforcement is active. This property can be used to temporarily suppress enforcement,
	 * e.g. to check what capability is required to perform an action by default, without the result
	 * being affected by custom policies.
	 *
	 * @var bool
	 */
	private $enforcementActive = true;

	const RELEVANT_POST_META_CAPS = [
		'read_post',
		'edit_post',
		'delete_post',

		//The "publish_post" meta cap seems to exist, but it's basically not used by WordPress core
		//except for one check in wp_insert_post() related to post slugs. It doesn't effectively control
		//who can publish posts, and we probably shouldn't use it in practice. Including it here for
		//completeness.
		'publish_post',
	];

	/**
	 * @var string[] Map of post type capabilities to core post meta capabilities.
	 *
	 * When registering a post type, you can specify that meta capabilities should map to certain
	 * custom capabilities. For example, "edit_post" could map to "foo". The custom capability is
	 * what will be passed to the "map_meta_cap" filter, so we need a way to know that "foo" means
	 * someone might be trying to edit a post. This array is used to map the custom capabilities back
	 * to the core post meta capabilities.
	 */
	private $supportedPostCapsToMetaCaps = [
		'read_page'    => 'read_post',
		'edit_page'    => 'edit_post',
		'delete_page'  => 'delete_post',
		'publish_page' => 'publish_post',
	];

	private $contentFilterDepth = 0;

	/**
	 * Temporary capabilities granted to specific users.
	 *
	 * @var array<int, array<string, true>>
	 */
	private $tempCapsByUser = [];
	/**
	 * @var bool
	 */
	private $userCapFilterAdded = false;

	public function __construct(
		Policy\ActionRegistry    $actionRegistry,
		ActorManager             $actorManager,
		Policy\PolicyStore       $policyStore,
		ContentPermissionsModule $module
	) {
		$this->actorManager = $actorManager;
		$this->policyStore = $policyStore;
		$this->actionRegistry = $actionRegistry;
		$this->module = $module;

		//The core meta caps map back to themselves. We could have a separate check for these,
		//but it's easier to just add them here.
		foreach (self::RELEVANT_POST_META_CAPS as $cap) {
			$this->supportedPostCapsToMetaCaps[$cap] = $cap;
		}

		//Collect the post type capabilities that map to core meta capabilities like "edit_post".
		add_action('registered_post_type', function ($postTypeName, $postType = null) {
			//Sanity check: $postType->cap should be an object.
			//Multiple users reported an "illegal offset" error with the "Bricks" theme/site builder,
			//which I can't reproduce since I don't have that theme. This check and the additional
			//isset()/is_string() checks later are intended to prevent that error.
			if ( !isset($postType->cap) || !is_object($postType->cap) ) {
				return;
			}

			foreach (self::RELEVANT_POST_META_CAPS as $cap) {
				if (
					isset($postType->cap->$cap)
					&& is_string($postType->cap->$cap)
					&& !isset($this->supportedPostCapsToMetaCaps[$postType->cap->$cap])
				) {
					$this->supportedPostCapsToMetaCaps[$postType->cap->$cap] = $cap;
				}
			}
		}, 10, 2);

		add_action('map_meta_cap', [$this, 'filterMetaCap'], 10, 4);

		add_action('wp_loaded', [$this, 'maybeRegisterPostVisibilityHooks']);
	}

	/**
	 * Register hooks that enforce visibility of posts.
	 *
	 * This should be called after the user (if any) has been authenticated.
	 *
	 * @return void
	 */
	public function maybeRegisterPostVisibilityHooks() {
		//Optimization: Since some of the filters we use to hide posts are triggered very often
		//(e.g. the "query" filter), let's only register our hooks if there are any posts that are
		//hidden from the current user.
		if ( $this->policyStore->mightHaveHiddenPosts($this->actorManager->getCurrentActor()) ) {
			$this->enablePostContentProtection();
			$this->enableDirectAccessProtection();
			$this->enablePostListFiltering();
			$this->enableAdjacentPostLinkFiltering();
		}
	}

	/**
	 * @param string $cap
	 * @param int $userId
	 * @param array $args
	 * @return array
	 */
	public function filterMetaCap($caps, $cap, $userId, $args = []) {
		if ( !$this->enforcementActive ) {
			return $caps;
		}

		//Is this one of the post capabilities we care about?
		//We must also have a post ID or a post object in the $args array.
		if ( !isset($this->supportedPostCapsToMetaCaps[$cap]) || !isset($args[0]) ) {
			return $caps;
		}
		$metaCap = $this->supportedPostCapsToMetaCaps[$cap];

		//Check if the user is allowed to perform that action.
		$result = $this->evaluatePostPolicy($args[0], $this->postCapToAction($metaCap), $userId);
		if ( $result ) {
			if ( $result->isDenied() ) {
				//Deny the action.
				$caps[] = 'do_not_allow';
			} else if (
				$result->isAllowed()
				//Compatibility: Don't override other plugins that explicitly block access.
				&& !in_array('do_not_allow', $caps)
			) {
				//Allow the action.

				//Usually, we don't need to do anything here. However, there's a special case where
				//an admin might give edit/delete permissions for a specific post to a role that normally
				//can't edit posts at all. In that case, we'll replace the original capability with
				//a special post-specific capability and grant that capability to the user.

				//Check if the user has the required capabilities.
				$user = \ameRoleUtils::get_user_by_id($userId);
				if ( ($user instanceof \WP_User) && $user->exists() ) {
					$hasAllCaps = $this->runWithoutPolicyEnforcement(function () use ($caps, $user) {
						foreach ($caps as $cap) {
							if ( !$user->has_cap($cap) ) {
								return false;
							}
						}
						return true;
					});

					if ( !$hasAllCaps ) {
						//Make up a unique capability for this situation and temporarily grant it to the user.
						$postSpecificCap = 'ame_vcap-' . $metaCap . '-u' . $userId . '-p' . intval($result->getObjectId());
						$this->grantTemporaryCapability($userId, $postSpecificCap);
						$caps = [$postSpecificCap];
					}
				}
			}
		}

		return $caps;
	}

	/**
	 * @param string $postCapability
	 * @return Action|null
	 */
	private function postCapToAction($postCapability) {
		switch ($postCapability) {
			case 'read_post':
				return $this->actionRegistry->getAction(ActionRegistry::ACTION_READ);
			case 'edit_post':
				return $this->actionRegistry->getAction(ActionRegistry::ACTION_EDIT);
			case 'delete_post':
				return $this->actionRegistry->getAction(ActionRegistry::ACTION_DELETE);
			case 'publish_post':
				return $this->actionRegistry->getAction(ActionRegistry::ACTION_PUBLISH);
			default:
				return null;
		}
	}

	private function enablePostContentProtection() {
		add_filter('the_content', [$this, 'protectPostContent'], self::CONTENT_FILTER_PRIORITY);
		add_filter('get_the_excerpt', [$this, 'protectPostContent'], self::CONTENT_FILTER_PRIORITY);
		add_filter('the_content_feed', [$this, 'protectPostContent'], self::CONTENT_FILTER_PRIORITY);

		//Hide comments on restricted posts.
		add_filter('comments_template', [$this, 'hideCommentsOnRestrictedPosts'], self::CONTENT_FILTER_PRIORITY);
		//Some themes don't use the comments_template() function, in which case the above
		//filter won't work. As backup, let's hide the text of each comment.
		add_filter('get_comment_text', [$this, 'protectPostContent'], self::CONTENT_FILTER_PRIORITY);
	}

	public function protectPostContent($content = '') {
		if ( !$this->enforcementActive ) {
			return $content;
		}

		if ( $this->contentFilterDepth > 0 ) {
			return $content; //Avoid recursion (see below).
		}

		$result = $this->evaluatePostPolicy(
			get_the_ID(),
			$this->actionRegistry->getAction(ActionRegistry::ACTION_READ)
		);

		if ( $result && $result->isDenied() ) {
			$replacementContent = $result->getPolicy()->getReplacementContent();

			//Apply WordPress filters to the replacement content. This is useful for shortcodes, etc.
			//Careful to avoid infinite recursion.
			$this->contentFilterDepth++;
			$replacementContent = apply_filters('the_content', $replacementContent);
			$this->contentFilterDepth--;

			return $replacementContent;
		}

		return $content;
	}

	public function hideCommentsOnRestrictedPosts($template) {
		if ( !$this->enforcementActive ) {
			return $template;
		}

		$result = $this->evaluatePostPolicy(
			get_the_ID(),
			$this->actionRegistry->getAction(ActionRegistry::ACTION_READ)
		);

		if ( $result && $result->isDenied() ) {
			//Check if there's a custom template for comments on hidden posts.
			//We use the same template name as plugins like "Members" and "Restrict Content Pro"
			//to make it easier for users to switch between plugins.
			$customTemplate = locate_template(['comments-no-access.php']);
			if ( $customTemplate ) {
				return $customTemplate;
			} else {
				return __DIR__ . '/templates/comments-no-access.php';
			}
		}

		return $template;
	}

	private function enableDirectAccessProtection() {
		add_filter('the_posts', [$this, 'restrictDirectPostAccess'], 10, 2);
		//Note: The "wp" action would be the next alternative to consider if "the_posts" turns out
		//to have any unexpected side effects or compatibility issues.
	}

	/**
	 * Restrict frontend access to individual posts that the user is not allowed to read.
	 *
	 * This applies to viewing a single post in the public-facing part of the site, like
	 * when the user tries to access a post via its permalink.
	 *
	 * @param array $posts
	 * @param \WP_Query|null $query
	 * @return array
	 */
	public function restrictDirectPostAccess($posts, $query = null) {
		if ( !$this->enforcementActive ) {
			return $posts;
		}

		if ( !$query || !is_array($posts) ) {
			return $posts;
		}

		if ( ($query instanceof \WP_Query) && $this->isDirectAccessQuery($query) ) {
			//Check if the user is allowed to view the post. There should be only one post,
			//given the above conditions, so we can just check the first one.
			$post = reset($posts);
			$result = $this->evaluatePostPolicy(
				$post,
				$this->actionRegistry->getAction(ActionRegistry::ACTION_READ)
			);
			if ( $result && $result->isDenied() ) {
				$protection = $result->getPolicy()->getDirectAccessProtection();
				if ( $protection ) {
					$filteredPosts = $protection->enforce($post, $posts, $query);
					if ( $filteredPosts !== null ) {
						$posts = $filteredPosts;
					}
				}

				//Note: Even if there is no specific $protection set, the post content
				//will be hidden by protectPostContent() later.
			}
		}
		return $posts;
	}

	private function isDirectAccessQuery(\WP_Query $query) {
		return $query->is_main_query() && $query->is_singular && !is_admin();
	}

	/**
	 * @param mixed $post
	 * @param Action|null $action
	 * @param int|null $userId
	 * @return EvaluationResult|null
	 */
	protected function evaluatePostPolicy($post, ?Action $action = null, $userId = null) {
		static $inProgress = [];

		//The action can be NULL for convenience, so that calling code doesn't have to check
		//if the action registry returned NULL.
		if ( $action === null ) {
			return null;
		}

		$postObject = null;

		//Get the post ID.
		if ( is_numeric($post) ) {
			$postId = intval($post);
		} else if ( $post instanceof \WP_Post ) {
			/** @noinspection PhpCastIsUnnecessaryInspection -- Just in case another plugin does something weird. */
			$postId = intval($post->ID);
			//Keep track of the post object if we have it, to save some processing later.
			$postObject = $post;
		} else {
			return null;
		}

		if ( $postId <= 0 ) {
			return null;
		}

		$userActor = $this->getEvaluationActor($userId);
		if ( !$userActor ) {
			return null;
		}

		//Prevent infinite recursion in case another plugin/theme triggers a capability check while
		//we're evaluating a policy. This can happen, for example, if a plugin calls current_user_can()
		//inside a filter like "get_post_metadata" (which we trigger when loading the post policy).
		$requestKey = $postId . '|' . $action->getName() . '|' . $userActor->getId();
		if ( isset($inProgress[$requestKey]) ) {
			return null;
		}
		$inProgress[$requestKey] = true;

		try {
			$result = $this->recursivelyEvaluatePostPolicy($postId, $postObject, $action, $userActor, $postId);
		} finally {
			unset($inProgress[$requestKey]);
		}
		return $result;
	}

	private function getEvaluationActor($user): ?Actor {
		if ( $user !== null ) {
			if ( $user instanceof Actor ) {
				return $user;
			}

			$userActor = $this->actorManager->getUserActorByUserId($user);
			if ( !$userActor ) {
				//This probably shouldn't happen - as far as I can tell by looking at the code,
				//WordPress always passes the real user ID to filters like "map_meta_cap". But
				//there is at least one plugin (PublishPress Permissions) that calls map_meta_cap()
				//with the user ID set to 0 in a certain context. In that case, we'll fall back to
				//the current user as a reasonable default.
				if ( is_numeric($user) && (intval($user) === 0) && is_user_logged_in() ) {
					$userActor = $this->actorManager->getCurrentUserActor();
				} else {
					//If the user ID is not NULL and also not 0, it's probably an actually invalid
					//user ID. In that case, we don't do anything.
					return null;
				}
			}
		} else {
			$userActor = $this->actorManager->getCurrentActor();
		}

		return $userActor;
	}

	/**
	 * @param int $postId
	 * @param \WP_Post|null $postObject
	 * @param Action $action
	 * @param Actor $userActor
	 * @param int $originalPostId
	 * @param ContentItemPolicy|null $objectPolicy
	 * @param int $depth
	 *
	 * @return EvaluationResult|null
	 */
	private function recursivelyEvaluatePostPolicy(
		$postId, $postObject, Action $action, Actor $userActor, $originalPostId,
		$objectPolicy = null, $depth = 0
	) {
		//todo: This whole thing could probably be cached for performance.
		//Avoid infinite recursion in case of a circular post hierarchy (which shouldn't happen).
		if ( $depth > 10 ) {
			return null;
		}

		//If we already have the post object, we can check if the post type is enabled
		//without having to potentially load the post from the database.
		$postTypeChecked = false;
		if ( isset($postObject->post_type) ) {
			if ( !$this->module->isPostTypeEnabled($postObject->post_type) ) {
				return null;
			}
			$postTypeChecked = true;
		}

		//Get the policy for this post.
		$postPolicy = $this->policyStore->getPostPolicy($postId);

		//Remember the policy of the original post in case we need to go up
		//the post hierarchy.
		if ( ($postId === $originalPostId) && !$objectPolicy ) {
			$objectPolicy = $postPolicy;
		}

		if ( $postPolicy ) {
			//Check if the post type is enabled. We try to avoid unnecessary database queries by
			//only loading the post (in get_post_type()) if it has a policy.
			if ( !$postTypeChecked && !$this->module->isPostTypeEnabled(get_post_type($postId)) ) {
				return null;
			}

			//Check if the policy lets the user perform the action.
			$result = $postPolicy->evaluate($userActor, $action, $objectPolicy, $originalPostId);
			if ( $result ) {
				return $result;
			}
		}

		//If there's no policy, or none of its settings apply to the current user,
		//let's try the parent post.
		$parentPostId = $this->getParentPostId($postId, $postObject);
		if ( $parentPostId ) {
			$parentResult = $this->recursivelyEvaluatePostPolicy(
				$parentPostId, null, $action, $userActor, $originalPostId,
				$objectPolicy, $depth + 1
			);
			if ( $parentResult ) {
				return $parentResult;
			}
		}

		//Check the term policies for the post's terms.
		//For performance, we only do this for the original post (depth 0), not for any parent posts.
		if (
			($depth === 0)
			&& $this->module->areTermPermissionsEnabled()
			&& !empty(self::POST_TO_TERM_ACTION_MAP[$action->getName()])
		) {
			$termAction = $this->actionRegistry->getAction(self::POST_TO_TERM_ACTION_MAP[$action->getName()]);
			if ( !$termAction ) {
				return null;
			}

			$terms = $this->getPostTermsForEvaluation($postId);
			if ( !empty($terms) ) {
				$postType = get_post_type($postId);
				$finalTermResult = null;

				foreach ($terms as $term) {
					$termResult = $this->evaluateTermPolicy(
						$term,
						$termAction,
						$postType,
						$userActor,
						$originalPostId
					);

					if ( $termResult ) {
						if ( $termResult->isAllowed() ) { //Allow overrides deny.
							return $termResult;
						}
						$finalTermResult = $termResult;
					}
				}

				if ( $finalTermResult ) {
					return $finalTermResult;
				}
			}
		}

		return null;
	}

	/**
	 * @param int $postId
	 * @param \WP_Post|null $postObject
	 * @return int|null
	 */
	private function getParentPostId($postId, $postObject = null) {
		if ( !$postObject ) {
			$postObject = get_post($postId);
		}
		if ( $postObject && $postObject->post_parent ) {
			/** @noinspection PhpCastIsUnnecessaryInspection */
			$parentPostId = intval($postObject->post_parent);
			if ( ($parentPostId > 0) && ($parentPostId !== $postId) ) {
				return $parentPostId;
			}
		}
		return null;
	}

	/**
	 * Get post terms for policy evaluation.
	 *
	 * This method should, where possible, optimize performance by caching, including only terms
	 * from enabled taxonomies, and terms that have policies defined (or that have parent terms with
	 * policies).
	 *
	 * @param int $postId
	 * @return array<\WP_Term>
	 */
	private function getPostTermsForEvaluation(int $postId): array {
		//TODO: Consider if "update_term_meta_cache" should be used here for performance.
		//TODO: Maybe there's a way to only get terms that have our metadata set? But we also need
		// to consider parent terms somehow.

		//Basic implementation: Get all terms from enabled taxonomies.
		$terms = wp_get_object_terms(
			$postId,
			array_keys($this->module->getEnabledTaxonomies())
		);

		if ( is_array($terms) ) {
			return $terms;
		} else {
			return [];
		}
	}

	/**
	 * @param int|\WP_Term|mixed $term
	 * @param Action|null $action
	 * @param string|null $postTypeName
	 * @param int|null|Actor $user
	 * @param int|null $originalObjectId
	 * @return EvaluationResult|null
	 */
	private function evaluateTermPolicy(
		$term,
		?Action $action,
		?string $postTypeName = null,
		$user = null,
		?int $originalObjectId = null
	): ?EvaluationResult {
		if ( $action === null ) {
			return null;
		}

		//Get the term ID.
		if ( is_numeric($term) ) {
			$termId = intval($term);
		} else if ( $term instanceof \WP_Term ) {
			/** @noinspection PhpCastIsUnnecessaryInspection */
			$termId = intval($term->term_id);
		} else {
			return null;
		}

		if ( $termId <= 0 ) {
			return null;
		}
		$userActor = $this->getEvaluationActor($user);
		if ( !$userActor ) {
			return null;
		}

		$termPolicy = $this->policyStore->getTermPolicy($termId, $postTypeName, true);
		if ( $termPolicy ) {
			return $termPolicy->evaluate(
				$userActor,
				$action,
				null,
				$originalObjectId ?? $termId
			);
		}

		return null;
	}

	public function enablePostListFiltering() {
		//Filter post queries to hide posts the user is not allowed to view in lists.
		add_filter('posts_clauses', [$this, 'filterPostQueryClauses'], 20, 2);

		//Exclude hidden posts from post counts.
		if ( is_admin() ) {
			//Other plugins like "PublishPress Permissions" can modify post count queries in a way
			//that makes the queries more difficult to identify. Let's use an early priority to try
			//to catch the unmodified query.
			add_filter('query', [$this, 'filterPostCountQuery'], 4);
		}
	}

	/**
	 * @param array $clauses
	 * @param \WP_Query $query
	 * @return array
	 */
	public function filterPostQueryClauses($clauses, $query = null) {
		if ( !$this->enforcementActive ) {
			return $clauses;
		}

		if ( !is_array($clauses) || !($query instanceof \WP_Query) ) {
			return $clauses;
		}

		//Skip queries that will be handled by the direct access filter.
		if ( $this->isDirectAccessQuery($query) ) {
			return $clauses;
		}

		//Avoid filtering wp_add_trashed_suffix_to_post_name_for_trashed_posts() calls.
		if (
			!empty($query->query_vars['name'])
			&& !empty($query->query_vars['post_status'])
			&& ($query->query_vars['post_status'] === 'trash')
			&& !empty($query->query_vars['post__not_in'])
		) {
			return $clauses;
		}

		$postTypes = $this->getPostTypesFromQuery($query);
		if ( is_array($postTypes) ) {
			//Filter out post types that are not enabled.
			$postTypes = array_filter($postTypes, [$this->module, 'isPostTypeEnabled']);
		} else {
			//Include all enabled post types.
			$postTypes = $this->module->getEnabledPostTypes();
		}

		$hiddenPostIds = $this->policyStore->getPostsHiddenFromLists(
			$this->actorManager->getCurrentActor(),
			$postTypes
		);

		if ( empty($hiddenPostIds) ) {
			return $clauses;
		}

		//Add a WHERE condition to exclude hidden posts by ID.
		global $wpdb;
		$clauses['where'] .= ' AND (' . $wpdb->posts . '.ID NOT IN (' . implode(',', $hiddenPostIds) . '))';

		return $clauses;
	}

	/**
	 * @param \WP_Query $query
	 * @return string[]|null
	 */
	private function getPostTypesFromQuery($query) {
		if ( !empty($query->query_vars['post_type']) ) {
			$postType = $query->query_vars['post_type'];
		} else if ( !empty($query->query['post_type']) ) {
			$postType = $query->query['post_type'];
		} else if ( $query->is_attachment ) {
			$postType = 'attachment';
		} else if ( $query->is_page ) {
			$postType = 'page';
		} else {
			$postType = 'any';
		}

		if ( ($postType === 'any') || ($postType === null) ) {
			return null;
		}

		if ( is_string($postType) ) {
			return [$postType];
		}
		if ( is_array($postType) ) {
			return $postType;
		}

		return null;
	}

	/**
	 * Filter database queries that count posts to exclude hidden posts.
	 *
	 * @param string $query
	 * @return string
	 */
	public function filterPostCountQuery($query) {
		global $wpdb, $typenow;
		$postsTable = $wpdb->posts;

		if ( !$this->enforcementActive ) {
			return $query;
		}

		$postType = null;
		$insertionPoint = null;

		//wp_count_posts() query:
		if ( strpos($query, 'SELECT post_status, COUNT( * ) AS num_posts ') !== false ) {
			$postTypeRegex = '/\bFROM\s+`?' . $postsTable . '`?\s+WHERE post_type\s*=\s*\'(?P<postType>[^ ]+)\'/i';
			if ( preg_match($postTypeRegex, $query, $matches, PREG_OFFSET_CAPTURE) ) {
				$postType = $matches['postType'][0];
				$insertionPoint = intval($matches[0][1]) + strlen($matches[0][0]);
			}
		}

		//"Mine" post count query in post tables:
		if (
			$typenow
			&& (strpos($query, 'SELECT COUNT( 1 )') !== false)
			&& (strpos($query, 'AND post_author = ') !== false)
		) {
			if ( preg_match(
				'/\bFROM\s+`?' . $postsTable . '`?\s+WHERE post_type\s*=\s*\'(?P<postType>[^ ]+)\'\s+/i',
				$query, $matches, PREG_OFFSET_CAPTURE
			) ) {
				$postType = $matches['postType'][0];
				$insertionPoint = intval($matches[0][1]) + strlen($matches[0][0]);
			}
		}

		if ( $postType && $insertionPoint && $this->module->isPostTypeEnabled($postType) ) {
			$hiddenPostIds = $this->policyStore->getPostsHiddenFromLists(
				$this->actorManager->getCurrentActor(),
				[$postType]
			);
			if ( !empty($hiddenPostIds) ) {
				$query = substr_replace(
					$query,
					' AND (' . $wpdb->posts . '.ID NOT IN (' . implode(',', $hiddenPostIds) . ')) ',
					$insertionPoint,
					0
				);
			}
		}

		return $query;
	}

	private function enableAdjacentPostLinkFiltering() {
		//Handle next post/previous post links. Posts hidden from post lists should also be hidden
		//from these links.
		add_filter("get_next_post_where", [$this, 'filterAdjacentPostWhere'], 10, 5);
		add_filter("get_previous_post_where", [$this, 'filterAdjacentPostWhere'], 10, 5);
	}

	/**
	 * @param string $where
	 * @param bool $inSameTerm
	 * @param int[]|string $excludedTerms
	 * @param string $taxonomy
	 * @param \WP_Post|null $post
	 * @return string
	 * @noinspection PhpUnusedParameterInspection -- Required by filter signature.
	 */
	public function filterAdjacentPostWhere(
		$where,
		//In theory, we don't need all these default values, but we'll include them in case another
		//plugin calls this filter with the wrong number of arguments.
		$inSameTerm = false,
		$excludedTerms = '',
		$taxonomy = 'category',
		$post = null
	) {
		//Sanity check: $post should always be provided and should be a WP_Post object.
		if ( !($post instanceof \WP_Post) ) {
			return $where;
		}

		$postType = $post->post_type;
		if ( !$this->module->isPostTypeEnabled($postType) ) {
			return $where;
		}

		//Briefly verify that the query still uses the expected table alias and still filters by
		//post type. This is true for WP 6.7, but could change in the future.
		if ( strpos($where, 'p.post_type') === false ) {
			return $where;
		}

		$hiddenPostIds = $this->policyStore->getPostsHiddenFromLists(
			$this->actorManager->getCurrentActor(),
			[$postType]
		);
		if ( empty($hiddenPostIds) ) {
			return $where;
		}

		$where .= ' AND (p.ID NOT IN (' . implode(',', $hiddenPostIds) . ')) ';

		return $where;
	}

	/**
	 * @param int $userId
	 * @param string $capability
	 */
	private function grantTemporaryCapability($userId, $capability) {
		if ( !isset($this->tempCapsByUser[$userId]) ) {
			$this->tempCapsByUser[$userId] = [];
		}
		$this->tempCapsByUser[$userId][$capability] = true;

		if ( !$this->userCapFilterAdded ) {
			//Note: The priority is chosen to Override PublishPress Permissions, which uses 99. This
			//shouldn't be a problem since our hook only adds caps for specific users and posts.
			add_filter('user_has_cap', [$this, 'filterUserCapabilities'], 199, 4);
			$this->userCapFilterAdded = true;
		}
	}

	/**
	 * Callback for the "user_has_cap" filter that enables temporary capabilities for specific users.
	 *
	 * Technically, all of the arguments should be passed by WordPress core, but we have defaults
	 * in case another plugin calls this filter with the wrong number of arguments.
	 *
	 * @param array $userCaps
	 * @param string[] $requiredCaps
	 * @param array $args
	 * @param \WP_User $user
	 * @noinspection PhpUnusedParameterInspection -- Required by filter signature.
	 */
	public function filterUserCapabilities($userCaps, $requiredCaps = [], $args = [], $user = null) {
		if ( !$this->enforcementActive || !($user instanceof \WP_User) ) {
			return $userCaps;
		}

		//Compatibility fix for ActivityPub 7.8.5.
		if ( !is_array($userCaps) ) {
			return $userCaps;
		}

		$userId = $user->ID;
		if ( isset($this->tempCapsByUser[$userId]) ) {
			foreach ($this->tempCapsByUser[$userId] as $cap => $unused) {
				$userCaps[$cap] = true;
			}
		}

		return $userCaps;
	}

	public function runWithoutPolicyEnforcement($callback, ...$callbackArgs) {
		$previousState = $this->enforcementActive;

		/** @noinspection PhpFieldImmediatelyRewrittenInspection -- Set for side effects. */
		$this->enforcementActive = false;
		$result = call_user_func($callback, ...$callbackArgs);
		$this->enforcementActive = $previousState;

		return $result;
	}

	public function disableEnforcement() {
		$this->enforcementActive = false;
	}

	public function enableEnforcement() {
		$this->enforcementActive = true;
	}

	public function isEnforcementActive() {
		return $this->enforcementActive;
	}
}
