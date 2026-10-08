<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

final class ProfileResponse
{
  use ResponseTrait;

  private string $displayName;

  private \stdClass $value;

  public function __construct(\stdClass $response)
  {
    $this->displayName = $this->getSessionProperty($response, 'displayName');
    $this->value = $response;
  }

  public function getDisplayname()
  {
    return $this->displayName;
  }
  public function getFollowersCount()
  {
    return $this->value->followersCount ?? 0;
  }
  public function getFollowsCount()
  {
    return $this->value->followsCount ?? 0;
  }
  public function getPostsCount()
  {
    return $this->value->postsCount ?? 0;
  }
}
