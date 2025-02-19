<?php

namespace Liquipedia\Extension\NetworkNotice\SpecialPage;

use Html;
use HTMLForm;
use Liquipedia\Extension\NetworkNotice\NoticeHtml;
use Status;
use Wikimedia\Rdbms\ILoadBalancer;

class SpecialNetworkNotice extends \SpecialPage {

	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
		parent::__construct( 'NetworkNotice', 'usenetworknotice' );
	}

	/**
	 * Group name of special page
	 * @return string group name
	 */
	public function getGroupName() {
		return 'liquipedia';
	}

	/**
	 * Main function of the Special Page
	 * @param string $par url slug after main page name
	 */
	public function execute( $par ) {
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}
		$output = $this->getOutput();
		$this->setHeaders();
		$params = explode( '/', $par ?? '' );
		$isEdit = $params[ 0 ] === 'edit'
			&& isset( $params[ 1 ] )
			&& !empty( $params[ 1 ] );

		if ( $params[ 0 ] === 'delete' && isset( $params[ 1 ] ) && !empty( $params[ 1 ] ) ) {
			$this->deleteNetworkNotice( $params[ 1 ] );
		}

		$formDefaults = [];
		if ( $isEdit ) {
			$output->addWikiTextAsContent( '=={{int:networknotice-edit-network-notice-heading}}==' );
			$res = $this->getNetworkNoticeById( $params[ 1 ] );
			if ( $res->numRows() > 0 ) {
				$row = $res->fetchObject();
				if ( $row->notice_id == $params[ 1 ] ) {
					$formDefaults[ 'NoticeId' ] = $row->notice_id;
					$formDefaults[ 'NoticeLabel' ] = $row->label;
					$formDefaults[ 'NoticeText' ] = $row->notice_text;
					$formDefaults[ 'NoticeNamespace' ] = $row->namespace;
					$formDefaults[ 'NoticeWiki' ] = $row->wiki;
					$formDefaults[ 'NoticeCategory' ] = $row->category;
					$formDefaults[ 'NoticePagePrefix' ] = $row->prefix;
					$formDefaults[ 'NoticeAction' ] = $row->action;
					$formDefaults[ 'NoticeDisabled' ] = $row->disabled;
				}
			}
		} else {
			$output->addWikiTextAsContent( '=={{int:networknotice-create-network-notice-heading}}==' );
		}

		$output->addWikiTextAsContent( '{{int:networknotice-create-notice-desc}}' );

		$formDescriptor = [];
		if ( $isEdit ) {
			$formDescriptor[ 'NoticeId' ] = [
				'type' => 'int',
				'label-message' => 'networknotice-edit-notice-id-label',
				'help-message' => 'networknotice-edit-notice-id-helper',
				'maxlength' => 100,
				'disabled' => true,
				'default' => ( $isEdit ? $formDefaults[ 'NoticeId' ] : '' )
			];
		}
		$formDescriptor[ 'NoticeLabel' ] = [
			'type' => 'text',
			'label-message' => 'networknotice-create-notice-label-label',
			'help-message' => 'networknotice-create-notice-label-helper',
			'maxlength' => 255,
			'required' => true,
			'default' => ( $isEdit ? $formDefaults[ 'NoticeLabel' ] : '' )
		];
		$formDescriptor[ 'NoticeText' ] = [
			'type' => 'textarea',
			'label-message' => 'networknotice-create-notice-text-label',
			'help-message' => 'networknotice-create-notice-text-helper',
			'maxlength' => 10000,
			'rows' => 10,
			'required' => true,
			'default' => ( $isEdit ? $formDefaults[ 'NoticeText' ] : '' )
		];
		$formDescriptor[ 'NoticeNamespace' ] = [
			'type' => 'text',
			'label-message' => 'networknotice-create-notice-namespace-label',
			'help-message' => 'networknotice-create-notice-namespace-helper',
			'maxlength' => 255,
			'default' => ( $isEdit ? $formDefaults[ 'NoticeNamespace' ] : '' )
		];
		$formDescriptor[ 'NoticeWiki' ] = [
			'type' => 'text',
			'label-message' => 'networknotice-create-notice-wiki-label',
			'help-message' => 'networknotice-create-notice-wiki-helper',
			'maxlength' => 255,
			'default' => ( $isEdit ? $formDefaults[ 'NoticeWiki' ] : '' )
		];
		$formDescriptor[ 'NoticeCategory' ] = [
			'type' => 'text',
			'label-message' => 'networknotice-create-notice-category-label',
			'help-message' => 'networknotice-create-notice-category-helper',
			'maxlength' => 255,
			'default' => ( $isEdit ? $formDefaults[ 'NoticeCategory' ] : '' )
		];
		$formDescriptor[ 'NoticePagePrefix' ] = [
			'type' => 'text',
			'label-message' => 'networknotice-create-notice-prefix-label',
			'help-message' => 'networknotice-create-notice-prefix-helper',
			'maxlength' => 255,
			'default' => ( $isEdit ? $formDefaults[ 'NoticePagePrefix' ] : '' )
		];
		$formDescriptor[ 'NoticeAction' ] = [
			'type' => 'text',
			'label-message' => 'networknotice-create-notice-action-label',
			'help-message' => 'networknotice-create-notice-action-helper',
			'maxlength' => 255,
			'default' => ( $isEdit ? $formDefaults[ 'NoticeAction' ] : '' )
		];
		$formDescriptor[ 'NoticeDisabled' ] = [
			'type' => 'check',
			'label-message' => 'networknotice-create-notice-disable-label',
			'help-message' => 'networknotice-create-notice-disable-helper',
			'maxlength' => 255,
			'default' => ( $isEdit ? $formDefaults[ 'NoticeDisabled' ] : '' )
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setFormIdentifier( 'createNetworkNotice' );
		$htmlForm
			->setSubmitTextMsg( 'networknotice-create-notice-'
				. ( $isEdit ? 'update' : 'create' )
				. '-button' )
			->setSubmitCallback( [ $this, 'createNetworkNoticeCB' ] );
		$htmlForm->addButton( [
			'name' => 'createNetworkNoticePreview',
			'value' => 'createNetworkNoticePreview',
			'label-message' => 'networknotice-create-notice-preview-button',
		] );
		$htmlForm->show();

		$output->addWikiTextAsContent( '=={{int:networknotice-all-network-notices-heading}}==' );

		$currentnotices = $this->getNetworkNotices();

		$table = Html::rawElement(
				'div',
				[
					'class' => 'table-responsive'
				],
				Html::rawElement(
					'table',
					[
						'class' => 'wikitable sortable'
					],
					Html::rawElement(
						'tr',
						[],
						Html::rawElement(
							'th',
							[],
							'{{int:networknotice-column-id-label}}'
						)
						. Html::rawElement(
							'th',
							[],
							'{{int:networknotice-column-name-label}}'
						)
						. Html::rawElement(
							'th',
							[],
							'{{int:networknotice-column-elements-label}}'
						)
						. Html::rawElement(
							'th',
							[],
							'{{int:networknotice-column-disabled-label}}'
						)
						. Html::rawElement(
							'th',
							[],
							'{{int:networknotice-column-edit-label}}'
						)
						. Html::rawElement(
							'th',
							[],
							'{{int:networknotice-column-delete-label}}'
						)
					)
					. ( function ( $currentnotices ) {
						$html = '';
						foreach ( $currentnotices as $row ) {
							$preContent = $this->msg( 'networknotice-create-notice-text-label' )->text()
								. ' ' . $row->notice_text . "\n";
							if ( $row->wiki ) {
								$preContent .= $this->msg( 'networknotice-create-notice-wiki-label' )->text()
									. ' ' . $row->wiki . "\n";
							}
							if ( $row->category ) {
								$preContent .= $this->msg( 'networknotice-create-notice-category-label' )->text()
									. ' ' . $row->category . "\n";
							}
							if ( $row->prefix ) {
								$preContent .= $this->msg( 'networknotice-create-notice-prefix-label' )->text()
									. ' ' . $row->prefix . "\n";
							}
							if ( $row->namespace ) {
								$preContent .= $this->msg( 'networknotice-create-notice-namespace-label' )->text()
									. ' ' . $row->namespace . "\n";
							}
							if ( $row->action ) {
								$preContent .= $this->msg( 'networknotice-create-notice-action-label' )->text()
									. ' ' . $row->action . "\n";
							}

							$html .= Html::rawElement(
									'tr',
									[],
									Html::rawElement(
										'td',
										[],
										$row->notice_id
									)
									. Html::rawElement(
										'td',
										[],
										$row->label
									)
									. Html::rawElement(
										'td',
										[],
										Html::rawElement(
											'pre',
											[],
											$preContent
										)
									)
									. Html::rawElement(
										'td',
										[],
										'{{int:networknotice-text-'
										. ( $row->disabled ? 'true' : 'false' )
										. '-label}}'
									)
									. Html::rawElement(
										'td',
										[],
										'[[Special:NetworkNotice/edit/' . $row->notice_id
										. '|{{int:networknotice-button-edit-label}}]]'
									)
									. Html::rawElement(
										'td',
										[],
										'[[Special:NetworkNotice/delete/' . $row->notice_id
										. '|{{int:networknotice-button-delete-label}}]]'
									)
							);
						}
						return $html;
					} )( $currentnotices )
				)
		);
		$output->addWikiTextAsContent( $table );
	}

	/**
	 * Callback for both create and edit form
	 * @param array $formData submitted data
	 * @return ?Status
	 */
	public function createNetworkNoticeCB( $formData ) {
		$request = $this->getRequest();
		if ( $request->getBool( 'createNetworkNoticePreview' ) ) {
			$output = $this->getOutput();
			$output->addWikiTextAsContent( '==={{int:networknotice-preview-heading}}===' );
			$output->addHTML(
				NoticeHtml::getNoticeHTML(
					$output,
					$formData[ 'NoticeText' ]
				)
			);
		} else {
			$vars = [
				'label' => $formData[ 'NoticeLabel' ],
				'notice_text' => $formData[ 'NoticeText' ],
				'namespace' => $formData[ 'NoticeNamespace' ],
				'wiki' => $formData[ 'NoticeWiki' ],
				'category' => $formData[ 'NoticeCategory' ],
				'prefix' => str_replace( '_', ' ', $formData[ 'NoticePagePrefix' ] ),
				'action' => $formData[ 'NoticeAction' ],
				'disabled' => $formData[ 'NoticeDisabled' ]
			];
			$status = new Status;
			if ( array_key_exists( 'NoticeId', $formData ) ) {
				$this->updateNetworkNotice( $vars, $formData[ 'NoticeId' ] );
				$status->warning( 'networknotice-success-updated' );
			} else {
				$this->createNetworkNotice( $vars );
				$status->warning( 'networknotice-success-created' );
			}
			return $status;
		}
	}

	/**
	 * @param array $vars
	 */
	private function createNetworkNotice( $vars ) {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY, [], $this->getConfig()->get( 'DBname' ) );
		$dbw->insert(
			'networknotice',
			$vars
		);
	}

	/**
	 * @param array $vars
	 * @param int $id
	 */
	private function updateNetworkNotice( $vars, $id ) {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY, [], $this->getConfig()->get( 'DBname' ) );
		$dbw->update(
			'networknotice',
			$vars,
			[
				'notice_id' => $id,
			]
		);
	}

	/**
	 * @return IResultWrapper
	 */
	private function getNetworkNotices() {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA, [], $this->getConfig()->get( 'DBname' ) );
		return $dbr->select(
				'networknotice',
				[
					'notice_id',
					'label',
					'notice_text',
					'wiki',
					'category',
					'prefix',
					'namespace',
					'action',
					'disabled'
				]
		);
	}

	/**
	 * @param int $id
	 * @return IResultWrapper
	 */
	private function getNetworkNoticeById( $id ) {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA, [], $this->getConfig()->get( 'DBname' ) );
		return $dbr->select(
				'networknotice',
				[
					'notice_id',
					'label',
					'notice_text',
					'wiki',
					'category',
					'prefix',
					'namespace',
					'action',
					'disabled'
				],
				[
					'notice_id' => $id
				]
		);
	}

	/**
	 * @param int $id
	 * @return bool
	 */
	private function deleteNetworkNotice( $id ) {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY, [], $this->getConfig()->get( 'DBname' ) );
		return $dbw->delete(
				'networknotice',
				[
					'notice_id' => $id
				]
		);
	}

}
