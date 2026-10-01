<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-puck-press-instagram-post-importer.php';

class Puck_Press_Instagram_CLI {

	/**
	 * Repairs Instagram posts with a missing featured image or missing image sizes.
	 *
	 * For each pp_insta_post without a featured image, links the post's own
	 * attachment as its featured image. For each linked image missing
	 * sub-sizes, generates only the missing sizes. Posts with no attachment,
	 * or whose image file is gone, are skipped and listed.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be repaired without changing anything.
	 *
	 * [--limit=<n>]
	 * : Repair at most this many posts. Default: no limit.
	 *
	 * ## EXAMPLES
	 *
	 *     wp puck-press insta-repair --dry-run
	 *     wp puck-press insta-repair
	 *
	 * @subcommand insta-repair
	 */
	public function insta_repair( $args, $assoc_args ) {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$limit   = isset( $assoc_args['limit'] ) ? max( 0, (int) $assoc_args['limit'] ) : 0;

		$importer = new Puck_Press_Instagram_Post_Importer();
		$report   = $importer->repair_post_images( $limit, $dry_run );

		WP_CLI::log( sprintf( 'Posts checked:              %d', $report['checked'] ) );
		WP_CLI::log( sprintf( 'Already healthy:            %d', $report['healthy'] ) );
		WP_CLI::log( sprintf( '%-28s%d', $dry_run ? 'Would relink thumbnail:' : 'Relinked thumbnail:', $report['relinked'] ) );
		WP_CLI::log( sprintf( '%-28s%d', $dry_run ? 'Would regenerate sizes:' : 'Regenerated sizes:', $report['regenerated'] ) );
		WP_CLI::log( sprintf( 'Left over (over --limit):   %d', $report['deferred'] ) );
		WP_CLI::log( sprintf( 'Resize failures:            %d', $report['errors'] ) );
		WP_CLI::log( sprintf( 'No attachment (skipped):    %d%s', count( $report['no_attachment'] ), $report['no_attachment'] ? ' — post IDs ' . implode( ', ', $report['no_attachment'] ) : '' ) );
		WP_CLI::log( sprintf( 'Image file missing:         %d%s', count( $report['file_missing'] ), $report['file_missing'] ? ' — post IDs ' . implode( ', ', $report['file_missing'] ) : '' ) );

		if ( $report['errors'] ) {
			WP_CLI::warning( 'Some images could not be resized; see the PHP error log.' );
		} else {
			WP_CLI::success( $dry_run ? 'Dry run complete. Nothing was changed.' : 'Repair complete.' );
		}
	}
}
