<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Site Pulse digest as inline-styled, table-based HTML that survives
 * Gmail/Outlook. Two modes:
 *   - 'review' : owner copy with an "internal review" banner + "Send to client" button.
 *   - 'client' : clean copy (no banner, no button) with a reply prompt.
 *
 * Which metric sections appear is driven by the pp_site_pulse_metrics toggles.
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes/site-pulse
 */
class Puck_Press_Site_Pulse_Email {

	const NAVY  = '#0b2545';
	const GREEN = '#1f9d57';
	const INK   = '#1f2733';
	const MUTED = '#5b6573';
	const LINE  = '#e6eaf0';

	/**
	 * @param array  $metrics     Output of Puck_Press_Site_Pulse_Metrics::collect().
	 * @param string $mode        'review' or 'client'.
	 * @param string $approve_url Confirm-flow URL (review mode only).
	 */
	public function render( array $metrics, string $mode = 'client', string $approve_url = '' ): string {
		$enabled = $this->enabled_metrics();

		ob_start();
		?>
<div style="background:#eceff3;padding:24px 0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:<?php echo self::INK; ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border:1px solid #dfe4ea;border-radius:10px;overflow:hidden;">

		<?php if ( $mode === 'review' ) : ?>
	<tr><td style="background:#fff4d6;border-bottom:1px solid #f0dca0;color:#7a5b00;padding:12px 22px;font-size:13px;line-height:1.5;">
		&#9888; <strong>Internal review copy — not yet sent to the client.</strong> Review below, then use “Send to client.”
	</td></tr>
		<?php endif; ?>

	<tr><td style="background:<?php echo self::NAVY; ?>;color:#ffffff;padding:28px 22px;text-align:center;">
		<div style="font-size:12px;letter-spacing:2px;opacity:.8;text-transform:uppercase;"><?php echo esc_html( $metrics['site_name'] ?? '' ); ?></div>
		<div style="font-size:26px;font-weight:800;margin-top:6px;">Your Site Pulse</div>
		<div style="font-size:14px;opacity:.85;margin-top:2px;"><?php echo esc_html( $metrics['month_label'] ?? '' ); ?></div>
	</td></tr>

	<tr><td style="padding:24px 22px;">
		<p style="font-size:16px;margin:0 0 20px;">Here’s what your website did for you last month. &#128071;</p>

		<?php
		if ( ! empty( $enabled['traffic'] ) ) {
			echo $this->traffic_card( $metrics['traffic'] ?? array() );
		}
		if ( ! empty( $enabled['content'] ) ) {
			echo $this->content_card( $metrics['content'] ?? array() );
		}
		if ( ! empty( $enabled['forms'] ) ) {
			echo $this->forms_card( (int) ( $metrics['content']['forms'] ?? 0 ) );
		}
		if ( ! empty( $enabled['schedule'] ) ) {
			echo $this->schedule_card( $metrics['schedule'] ?? array() );
		}
		if ( ! empty( $enabled['season'] ) ) {
			echo $this->season_card( $metrics['season'] ?? array() );
		}
		?>

		<?php if ( $mode === 'review' && $approve_url !== '' ) : ?>
		<div style="text-align:center;margin:26px 0 6px;">
			<a href="<?php echo esc_url( $approve_url ); ?>" style="display:inline-block;background:<?php echo self::GREEN; ?>;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 28px;border-radius:8px;font-size:15px;">&#9989;&nbsp; Send to client</a>
			<div style="font-size:12px;color:#8a94a3;margin-top:10px;">Opens a confirmation page — nothing sends until you click “Confirm” there.</div>
		</div>
		<?php elseif ( $mode === 'client' ) : ?>
		<p style="font-size:14px;color:<?php echo self::MUTED; ?>;margin:22px 0 0;">Questions about your site? Just reply to this email.</p>
		<?php endif; ?>
	</td></tr>

	<tr><td style="background:#f6f8fb;color:#8a94a3;font-size:12px;text-align:center;padding:14px;">
		<?php if ( $mode === 'review' ) : ?>
			Generated automatically by Puck Press · <?php echo esc_html( $metrics['generated'] ?? '' ); ?>
		<?php else : ?>
			Powered by Puck Press
		<?php endif; ?>
	</td></tr>

</table>
</td></tr></table>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private function traffic_card( array $t ): string {
		$views      = (int) ( $t['views'] ?? 0 );
		$visits     = (int) ( $t['visits'] ?? 0 );
		$comparison = $t['comparison'] ?? array();
		$badge      = $this->comparison_badge( $comparison );

		ob_start();
		?>
		<?php echo $this->card_open( 'Website traffic' ); ?>
			<div style="margin-top:10px;">
				<span style="font-size:34px;font-weight:800;color:<?php echo self::NAVY; ?>;"><?php echo esc_html( number_format_i18n( $views ) ); ?></span>
				<span style="color:<?php echo self::MUTED; ?>;"> page views</span>
				<?php echo $badge; ?>
			</div>
			<div style="color:<?php echo self::MUTED; ?>;margin-top:4px;"><?php echo esc_html( number_format_i18n( $visits ) ); ?> visits</div>
			<?php if ( ! empty( $t['top_pages'] ) ) : ?>
			<div style="margin-top:14px;font-size:13px;color:#3a434f;">
				<strong>Most-viewed pages</strong>
				<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;font-size:13px;">
					<?php foreach ( $t['top_pages'] as $i => $page ) : ?>
					<tr>
						<td style="padding:2px 0;"><?php echo esc_html( ( $i + 1 ) . '. ' . ( $page['label'] ?? $page['path'] ?? '' ) ); ?></td>
						<td align="right" style="color:<?php echo self::MUTED; ?>;padding:2px 0;"><?php echo esc_html( number_format_i18n( (int) ( $page['views'] ?? 0 ) ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</table>
			</div>
			<?php endif; ?>
		<?php echo $this->card_close(); ?>
		<?php
		return (string) ob_get_clean();
	}

	private function content_card( array $c ): string {
		$recaps = (int) ( $c['recaps'] ?? 0 );
		$insta  = (int) ( $c['insta'] ?? 0 );
		ob_start();
		?>
		<?php echo $this->card_open( 'Content we published for you' ); ?>
			<div style="margin-top:10px;font-size:15px;">&#128240; <strong><?php echo esc_html( number_format_i18n( $recaps ) ); ?></strong> game recap<?php echo $recaps === 1 ? '' : 's'; ?> auto-written &amp; posted</div>
			<div style="margin-top:6px;font-size:15px;">&#128247; <strong><?php echo esc_html( number_format_i18n( $insta ) ); ?></strong> Instagram post<?php echo $insta === 1 ? '' : 's'; ?> pulled onto the site</div>
		<?php echo $this->card_close(); ?>
		<?php
		return (string) ob_get_clean();
	}

	private function forms_card( int $count ): string {
		// Hidden when there were no submissions, so a quiet month never shows a "0".
		if ( $count <= 0 ) {
			return '';
		}
		ob_start();
		?>
		<?php echo $this->card_open( 'Contact form submissions' ); ?>
			<div style="margin-top:10px;font-size:15px;">&#128236; <strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong> message<?php echo $count === 1 ? '' : 's'; ?> received through your website</div>
		<?php echo $this->card_close(); ?>
		<?php
		return (string) ob_get_clean();
	}

	private function schedule_card( array $s ): string {
		$tracked = (int) ( $s['tracked'] ?? 0 );
		$scored  = (int) ( $s['scored'] ?? 0 );
		ob_start();
		?>
		<?php echo $this->card_open( 'Schedule kept up to date' ); ?>
			<div style="margin-top:10px;font-size:15px;">&#128197; <strong><?php echo esc_html( number_format_i18n( $tracked ) ); ?></strong> game<?php echo $tracked === 1 ? '' : 's'; ?> this month &nbsp;·&nbsp; <strong><?php echo esc_html( number_format_i18n( $scored ) ); ?></strong> final score<?php echo $scored === 1 ? '' : 's'; ?> updated</div>
		<?php echo $this->card_close(); ?>
		<?php
		return (string) ob_get_clean();
	}

	private function season_card( array $season ): string {
		$record       = $season['record'] ?? array();
		$standings    = $season['standings'] ?? array();
		$awards_added = (int) ( $season['awards_added'] ?? 0 );
		$record_str   = $this->format_record( $record );

		ob_start();
		?>
		<?php echo $this->card_open( 'Season snapshot' ); ?>
			<?php if ( $record_str !== '' ) : ?>
			<div style="margin-top:10px;font-size:15px;">Record: <strong><?php echo esc_html( $record_str ); ?></strong></div>
			<?php endif; ?>
			<?php foreach ( $standings as $s ) : ?>
				<?php $line = $this->format_standing( $s ); ?>
				<?php if ( $line !== '' ) : ?>
				<div style="margin-top:6px;font-size:15px;"><?php echo esc_html( $line ); ?></div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $awards_added > 0 ) : ?>
			<div style="margin-top:6px;font-size:15px;">&#127942; <?php echo esc_html( $awards_added . ' player ' . ( $awards_added === 1 ? 'honor' : 'honors' ) . ' added' ); ?></div>
			<?php endif; ?>
		<?php echo $this->card_close(); ?>
		<?php
		return (string) ob_get_clean();
	}

	private function comparison_badge( array $comparison ): string {
		if ( ! empty( $comparison['is_new'] ) ) {
			return $this->badge( 'New this month', self::GREEN );
		}
		if ( isset( $comparison['percent'] ) && $comparison['percent'] !== null ) {
			$pct = (int) $comparison['percent'];
			if ( $pct >= 0 ) {
				return $this->badge( '▲ ' . $pct . '% vs. last month', self::GREEN );
			}
			return $this->badge( '▼ ' . abs( $pct ) . '% vs. last month', '#b4453a' );
		}
		return '';
	}

	private function badge( string $text, string $color ): string {
		$bg = $color === self::GREEN ? '#e4f7ec' : '#fbe7e4';
		return '<span style="background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';font-weight:700;font-size:13px;padding:3px 8px;border-radius:20px;margin-left:8px;white-space:nowrap;">' . esc_html( $text ) . '</span>';
	}

	private function format_record( array $r ): string {
		if ( empty( $r ) ) {
			return '';
		}
		$w = (int) ( $r['wins'] ?? 0 );
		$l = (int) ( $r['losses'] ?? 0 );
		$o = (int) ( $r['otl'] ?? 0 );
		$t = (int) ( $r['ties'] ?? 0 );
		if ( $w === 0 && $l === 0 && $o === 0 && $t === 0 ) {
			return '';
		}
		$str = $w . '–' . $l . '–' . $o;
		if ( $t > 0 ) {
			$str .= '–' . $t;
		}
		return $str;
	}

	private function format_standing( array $s ): string {
		$division = trim( (string) ( $s['division'] ?? '' ) );
		$rank     = $s['rank'] ?? null;
		if ( ! $rank || $division === '' ) {
			return '';
		}
		$team = trim( (string) ( $s['team'] ?? '' ) );
		$pos  = $this->ordinal( (int) $rank ) . ' in ' . $division;
		return $team !== '' ? $team . ': ' . $pos : $pos;
	}

	private function ordinal( int $n ): string {
		$suffix = 'th';
		if ( $n % 100 < 11 || $n % 100 > 13 ) {
			switch ( $n % 10 ) {
				case 1: $suffix = 'st'; break;
				case 2: $suffix = 'nd'; break;
				case 3: $suffix = 'rd'; break;
			}
		}
		return $n . $suffix;
	}

	private function card_open( string $title ): string {
		return '<div style="border:1px solid ' . self::LINE . ';border-radius:10px;padding:18px;margin-bottom:16px;">'
			. '<div style="font-size:12px;letter-spacing:1px;color:#8a94a3;font-weight:700;text-transform:uppercase;">' . esc_html( $title ) . '</div>';
	}

	private function card_close(): string {
		return '</div>';
	}

	/**
	 * Metric section toggles; default all on.
	 *
	 * @return array<string,bool>
	 */
	private function enabled_metrics(): array {
		$saved = get_option( Puck_Press_Site_Pulse::OPTION_METRICS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		// A key that's absent (e.g. a metric added after this config was last saved,
		// or a never-configured site) defaults to ON. Only an explicit 0 turns it off.
		$out = array();
		foreach ( array( 'traffic', 'content', 'forms', 'schedule', 'season' ) as $key ) {
			$out[ $key ] = ! array_key_exists( $key, $saved ) || ! empty( $saved[ $key ] );
		}
		return $out;
	}
}
